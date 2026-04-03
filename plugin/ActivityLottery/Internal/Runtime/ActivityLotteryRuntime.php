<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Runtime;

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStore;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraClaimRewardNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraFollowNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraShareNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraWatchLiveNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraWatchVideoNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\ExecuteDrawNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\FinalClaimRewardNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\FinalizeFlowNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\LoadActivitySnapshotNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\NodeRunnerInterface;
use Bhp\Plugin\ActivityLottery\Internal\Node\NotifyDrawResultNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\ParseEraPageNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\RecordDrawResultNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\RefreshDrawTimesNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\ValidateActivityNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageSnapshot;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowBudget;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowPool;
use Bhp\Scheduler\TaskResult;
use RuntimeException;

final class ActivityLotteryRuntime
{
    /** @var array<string, NodeRunnerInterface> */
    private array $runnerMap = [];

    public function __construct(
        private readonly ActivityCatalogLoader $catalogLoader,
        private readonly ActivityFlowStore $flowStore,
        array $runners = [],
        private readonly ActivityFlowPlanner $planner = new ActivityFlowPlanner(),
        private readonly ActivityFlowPool $flowPool = new ActivityFlowPool(new ActivityFlowBudget(4, 6, 3000)),
        private readonly ActivityLotteryClock $clock = new ActivityLotteryClock(),
        private readonly ActivityLotteryWindow $window = new ActivityLotteryWindow('06:00:00', '23:00:00'),
        private readonly string $windowStartAt = '06:00:00',
        private readonly string $windowEndAt = '23:00:00',
    ) {
        foreach (array_merge($this->defaultRunners(), $runners) as $runner) {
            if ($runner instanceof NodeRunnerInterface) {
                $this->runnerMap[$runner->type()] = $runner;
            }
        }
    }

    public function bizDate(): string
    {
        return date('Y-m-d', $this->clock->now());
    }

    public function tick(): TaskResult
    {
        $now = $this->clock->now();
        if (!$this->window->contains($now)) {
            return TaskResult::after($this->secondsUntilWindowStart($now));
        }

        $bizDate = $this->bizDate();
        $flows = [];
        foreach ($this->flowStore->load($bizDate) as $flow) {
            $flows[$flow->id()] = $flow;
        }

        foreach ($this->catalogLoader->load() as $item) {
            $planned = $this->planner->plan($item, null, $bizDate);
            if (!isset($flows[$planned->id()])) {
                $flows[$planned->id()] = $planned;
            }
        }

        $tickStartedAtMs = (int)round(microtime(true) * 1000);
        $pickedFlows = $this->flowPool->pick(array_values($flows), $now, $tickStartedAtMs);
        foreach ($pickedFlows as $flow) {
            $startedAt = microtime(true);
            $updated = $this->executeFlow($flow, $now);
            $flows[$updated->id()] = $updated;
            $this->flowPool->noteStepExecuted($tickStartedAtMs, $flow->id(), (microtime(true) - $startedAt) * 1000);
        }

        $this->flowStore->save(array_values($flows));

        return TaskResult::after($this->resolveNextDelaySeconds(array_values($flows), $now));
    }

    /**
     * @return NodeRunnerInterface[]
     */
    private function defaultRunners(): array
    {
        return [
            new LoadActivitySnapshotNodeRunner(),
            new ValidateActivityNodeRunner(),
            new ParseEraPageNodeRunner(),
            new EraShareNodeRunner(),
            new EraFollowNodeRunner(),
            new EraClaimRewardNodeRunner(),
            new EraWatchVideoNodeRunner('era_task_watch_video_fixed'),
            new EraWatchVideoNodeRunner('era_task_watch_video_topic'),
            new EraWatchLiveNodeRunner(),
            new RefreshDrawTimesNodeRunner(),
            new ExecuteDrawNodeRunner(),
            new RecordDrawResultNodeRunner(),
            new NotifyDrawResultNodeRunner(),
            new FinalClaimRewardNodeRunner(),
            new FinalizeFlowNodeRunner(),
        ];
    }

    private function executeFlow(ActivityFlow $flow, int $now): ActivityFlow
    {
        $currentNode = $flow->nodes()[$flow->currentNodeIndex()];
        if (in_array($currentNode->status(), [ActivityNodeStatus::SUCCEEDED, ActivityNodeStatus::SKIPPED], true)) {
            return $this->advanceWithoutRunner($flow, $now);
        }

        $runner = $this->runnerMap[$currentNode->type()] ?? null;
        if (!$runner instanceof NodeRunnerInterface) {
            throw new RuntimeException('缺少节点执行器: ' . $currentNode->type());
        }

        $result = $runner->run($flow, $currentNode, $now);
        return $this->applyNodeResult($flow, $result, $now);
    }

    private function advanceWithoutRunner(ActivityFlow $flow, int $now): ActivityFlow
    {
        $row = $flow->toArray();
        if ($row['current_node_index'] < (count($row['nodes']) - 1)) {
            $row['current_node_index']++;
            $row['status'] = ActivityFlowStatus::PENDING;
            $row['next_run_at'] = 0;
        } else {
            $row['status'] = ActivityFlowStatus::COMPLETED;
        }
        $row['updated_at'] = $now;

        return ActivityFlow::fromArray($row);
    }

    private function applyNodeResult(ActivityFlow $flow, object $result, int $now): ActivityFlow
    {
        $row = $flow->toArray();
        $currentIndex = $row['current_node_index'];
        $payload = method_exists($result, 'payload') && is_array($result->payload())
            ? $result->payload()
            : [];

        if (is_array($payload['context_patch'] ?? null)) {
            $row['context'] = array_replace(
                is_array($row['context'] ?? null) ? $row['context'] : [],
                $payload['context_patch'],
            );
        }

        if (is_array($payload['node_payload_patch'] ?? null)) {
            $row['nodes'][$currentIndex]['payload'] = array_replace(
                is_array($row['nodes'][$currentIndex]['payload'] ?? null) ? $row['nodes'][$currentIndex]['payload'] : [],
                $payload['node_payload_patch'],
            );
        }

        $nodeStatus = trim((string)($payload['node_status'] ?? ''));
        if ($nodeStatus === '') {
            $nodeStatus = method_exists($result, 'ok') && $result->ok()
                ? ActivityNodeStatus::SUCCEEDED
                : ActivityNodeStatus::FAILED;
        }
        $row['nodes'][$currentIndex]['status'] = $nodeStatus;
        $row['nodes'][$currentIndex]['result'] = method_exists($result, 'toArray')
            ? $result->toArray()
            : null;
        $row['updated_at'] = $now;
        $row['next_run_at'] = is_int($payload['next_run_at'] ?? null) ? $payload['next_run_at'] : 0;

        if ($row['nodes'][$currentIndex]['type'] === 'parse_era_page' && $nodeStatus === ActivityNodeStatus::SUCCEEDED) {
            $row = $this->expandFlowAfterParse($row);
        }

        $explicitFlowStatus = trim((string)($payload['flow_status'] ?? ''));
        if ($explicitFlowStatus !== '') {
            $row['status'] = $explicitFlowStatus;
        } else {
            $row['status'] = match ($nodeStatus) {
                ActivityNodeStatus::WAITING => ActivityFlowStatus::BLOCKED,
                ActivityNodeStatus::FAILED => ActivityFlowStatus::FAILED,
                ActivityNodeStatus::SKIPPED, ActivityNodeStatus::SUCCEEDED => ActivityFlowStatus::PENDING,
                default => ActivityFlowStatus::RUNNING,
            };
        }

        if (
            $explicitFlowStatus === ''
            && in_array($nodeStatus, [ActivityNodeStatus::SUCCEEDED, ActivityNodeStatus::SKIPPED], true)
        ) {
            if ($currentIndex < (count($row['nodes']) - 1)) {
                $row['current_node_index'] = $currentIndex + 1;
                $row['next_run_at'] = 0;
            } else {
                $row['status'] = ActivityFlowStatus::COMPLETED;
            }
        }

        return ActivityFlow::fromArray($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function expandFlowAfterParse(array $row): array
    {
        $pageSnapshotRow = $row['context']['era_page_snapshot'] ?? null;
        if (!is_array($pageSnapshotRow)) {
            return $row;
        }

        $pageSnapshot = EraPageSnapshot::fromArray($pageSnapshotRow);
        $item = ActivityCatalogItem::fromArray(is_array($row['activity'] ?? null) ? $row['activity'] : []);
        $planned = $this->planner->plan($item, $pageSnapshot, (string)$row['biz_date'])->toArray();

        for ($index = 0; $index <= 2; $index++) {
            if (isset($row['nodes'][$index], $planned['nodes'][$index])) {
                $planned['nodes'][$index] = $row['nodes'][$index];
            }
        }

        $row['nodes'] = $planned['nodes'];
        return $row;
    }

    /**
     * @param ActivityFlow[] $flows
     */
    private function resolveNextDelaySeconds(array $flows, int $now): float
    {
        $nextDelay = null;
        foreach ($flows as $flow) {
            if (!in_array($flow->status(), [
                ActivityFlowStatus::PENDING,
                ActivityFlowStatus::RUNNING,
                ActivityFlowStatus::BLOCKED,
            ], true)) {
                continue;
            }

            $delay = $flow->nextRunAt() > $now
                ? max(1, $flow->nextRunAt() - $now)
                : 1;
            $nextDelay = $nextDelay === null ? $delay : min($nextDelay, $delay);
        }

        return (float)($nextDelay ?? 300);
    }

    private function secondsUntilWindowStart(int $now): float
    {
        [$hour, $minute, $second] = $this->parseTime($this->windowStartAt);
        $target = strtotime(date('Y-m-d', $now) . sprintf(' %02d:%02d:%02d', $hour, $minute, $second));
        if ($target === false || $target <= $now) {
            $target = strtotime(date('Y-m-d', strtotime('+1 day', $now)) . sprintf(' %02d:%02d:%02d', $hour, $minute, $second));
        }

        return max(1.0, (float)($target - $now));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function parseTime(string $time): array
    {
        $chunks = explode(':', trim($time));
        return [
            (int)($chunks[0] ?? 0),
            (int)($chunks[1] ?? 0),
            (int)($chunks[2] ?? 0),
        ];
    }
}
