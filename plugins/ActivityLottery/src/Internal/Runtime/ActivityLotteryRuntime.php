<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Runtime;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStore;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraClaimRewardNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraFollowNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraShareNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraUnfollowNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraWatchLiveNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraWatchProgress;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraWatchVideoNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ExecuteDrawNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\FinalClaimRewardNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\FinalizeFlowNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\LoadActivitySnapshotNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\NodeRunnerInterface;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\NotifyDrawResultNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ParseEraPageNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\RecordDrawResultNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\RefreshDrawTimesNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ResolvedActivityView;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ResolvedEraTaskView;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ValidateActivityNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraPageSnapshot;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Pool\ActivityFlowBudget;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Pool\ActivityFlowPool;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\NoLoginException;
use RuntimeException;

final class ActivityLotteryRuntime
{
    /** @var array<string, NodeRunnerInterface> */
    private array $runnerMap = [];
    private \Closure $logger;
    private readonly ActivityLotteryLifecycleLogger $lifecycleLogger;

    /**
     * 初始化 ActivityLotteryRuntime
     * @param ActivityCatalogLoader $catalogLoader
     * @param ActivityFlowStore $flowStore
     * @param ActivityLotteryWindow $window
     * @param string $windowStartAt
     * @param string $windowEndAt
     * @param array $runners
     * @param ActivityFlowPlanner $planner
     * @param ActivityFlowPool $flowPool
     * @param ActivityLotteryClock $clock
     * @param callable $logger
     */
    public function __construct(
        private readonly ActivityCatalogLoader $catalogLoader,
        private readonly ActivityFlowStore $flowStore,
        private readonly ActivityLotteryWindow $window,
        private readonly string $windowStartAt,
        private readonly string $windowEndAt,
        array $runners = [],
        private readonly ActivityFlowPlanner $planner = new ActivityFlowPlanner(),
        private readonly ActivityFlowPool $flowPool = new ActivityFlowPool(new ActivityFlowBudget(4, 6, 3000)),
        private readonly ActivityLotteryClock $clock = new ActivityLotteryClock(),
        ?callable $logger = null,
    ) {
        $this->logger = $logger !== null
            ? \Closure::fromCallable($logger)
            : static function (string $level, string $message, array $context = []): void {
            };
        $this->lifecycleLogger = new ActivityLotteryLifecycleLogger();
        foreach (array_merge($this->defaultRunners(), $runners) as $runner) {
            if ($runner instanceof NodeRunnerInterface) {
                $this->runnerMap[$runner->type()] = $runner;
            }
        }
    }

    /**
     * 处理biz日期
     * @return string
     */
    public function bizDate(): string
    {
        return date('Y-m-d', $this->clock->now());
    }

    /**
     * 处理tick
     * @return TaskResult
     */
    public function tick(): TaskResult
    {
        $now = $this->clock->now();
        $bizDate = $this->bizDate();
        if (!$this->window->contains($now)) {
            $delay = $this->secondsUntilWindowStart($now);
            $this->log('info', 'ActivityLottery 当前不在运行窗口内，跳过本轮', [
                'event' => 'tick.outside_window',
                'biz_date' => $bizDate,
                'next_delay_seconds' => $delay,
            ]);

            return TaskResult::after($delay);
        }

        $catalog = $this->catalogLoader->load();
        $plannedCatalogFlows = [];
        foreach ($catalog as $item) {
            $planned = $this->planner->plan($item, null, $bizDate);
            $plannedCatalogFlows[$planned->id()] = $planned;
        }

        $flows = [];
        foreach ($this->flowStore->load($bizDate) as $flow) {
            $flows[$flow->id()] = $flow;
        }

        $loadedFlowCount = count($flows);
        $prunedFlowCount = 0;
        if ($plannedCatalogFlows !== []) {
            $flows = array_filter(
                $flows,
                fn (ActivityFlow $flow): bool => isset($plannedCatalogFlows[$flow->id()]),
            );
            $prunedFlowCount = $loadedFlowCount - count($flows);
        }
        $existingFlowCount = count($flows);

        $newFlowCount = 0;
        foreach ($plannedCatalogFlows as $planned) {
            if (!isset($flows[$planned->id()])) {
                $flows[$planned->id()] = $planned;
                $newFlowCount++;
            }
        }

        $tickStartedAtMs = (int)round(microtime(true) * 1000);
        $pickedFlows = $this->flowPool->pick(array_values($flows), $now, $tickStartedAtMs);
        foreach ($pickedFlows as $flow) {
            $startedAt = microtime(true);
            $currentNode = $flow->nodes()[$flow->currentNodeIndex()];
            $updated = $this->executeFlow($flow, $now);
            $flows[$updated->id()] = $updated;
            $this->flowPool->noteStepExecuted($tickStartedAtMs, $flow->id(), (microtime(true) - $startedAt) * 1000);
            $executedNodeIndex = min($flow->currentNodeIndex(), count($updated->nodes()) - 1);
            $executedNode = $updated->nodes()[$executedNodeIndex];
            $updatedNodeIndex = min($updated->currentNodeIndex(), count($updated->nodes()) - 1);
            $updatedNode = $updated->nodes()[$updatedNodeIndex];
            $result = $executedNode->result();
            [$resultMessage, $resultContext] = $this->lifecycleLogger->buildNodeResultLog($flow, $currentNode, $updated, $executedNode);
            $resultLogContext = array_replace([
                'event' => 'node.result',
                'biz_date' => $bizDate,
                'flow_id' => $updated->id(),
                'node_type' => $currentNode->type(),
                'node_status' => $executedNode->status(),
                'flow_status' => $updated->status(),
                'next_run_at' => $updated->nextRunAt(),
                'current_node_index' => $updated->currentNodeIndex(),
                'next_node_type' => $updatedNode->type(),
                'node_message' => $result?->message() ?? '',
            ], $resultContext);
            if ($this->lifecycleLogger->shouldEmitLifecycleLog(
                'node.result',
                $updated->id(),
                $currentNode->type(),
                $executedNode->status(),
                $resultLogContext,
                $now,
            )) {
                $this->log($this->lifecycleLogger->resolveNodeResultLogLevel($currentNode->type(), $executedNode, $resultLogContext), $resultMessage, $resultLogContext);
            }
        }

        $this->flowStore->save(array_values($flows));

        $delay = $this->resolveNextDelaySeconds(array_values($flows), $now);
        $this->log('debug', sprintf(
            'ActivityLottery 本轮完成: 目录 %d 个，已有 flow %d 个，新增 %d 个，本轮执行 %d 个，下次 %s后继续',
            count($catalog),
            $existingFlowCount,
            $newFlowCount,
            count($pickedFlows),
            $this->formatDelayLabel($delay),
        ), [
            'event' => 'tick.finish',
            'biz_date' => $bizDate,
            'catalog_count' => count($catalog),
            'loaded_flow_count' => $loadedFlowCount,
            'existing_flow_count' => $existingFlowCount,
            'pruned_flow_count' => $prunedFlowCount,
            'flow_count' => count($flows),
            'new_flow_count' => $newFlowCount,
            'picked_flow_count' => count($pickedFlows),
            'next_delay_seconds' => $delay,
        ]);

        return TaskResult::after($delay);
    }

    /**
     * @return NodeRunnerInterface[]
     */
    private function defaultRunners(): array
    {
        return [
            new ValidateActivityNodeRunner(),
            new RecordDrawResultNodeRunner(),
            new FinalizeFlowNodeRunner(),
        ];
    }

    /**
     * 执行流程
     * @param ActivityFlow $flow
     * @param int $now
     * @return ActivityFlow
     */
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

        try {
            $result = $runner->run($flow, $currentNode, $now);
        } catch (NoLoginException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            $result = new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult(
                false,
                $throwable->getMessage() !== '' ? $throwable->getMessage() : '节点执行异常',
                [
                    'node_status' => ActivityNodeStatus::FAILED,
                ],
                $now,
            );
        }

        return $this->applyNodeResult($flow, $result, $now);
    }

    /**
     * 推进WithoutRunner
     * @param ActivityFlow $flow
     * @param int $now
     * @return ActivityFlow
     */
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

    /**
     * 应用节点结果
     * @param ActivityFlow $flow
     * @param object $result
     * @param int $now
     * @return ActivityFlow
     */
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

    /**
     * 处理secondsUntil窗口Start
     * @param int $now
     * @return float
     */
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
     * 格式化延迟Label
     * @param float $seconds
     * @return string
     */
    private function formatDelayLabel(float $seconds): string
    {
        $delay = max(1, (int)ceil($seconds));
        if ($delay % 3600 === 0 && $delay >= 3600) {
            return sprintf('%d 小时', (int)($delay / 3600));
        }
        if ($delay % 60 === 0 && $delay >= 60) {
            return sprintf('%d 分钟', (int)($delay / 60));
        }

        return sprintf('%d 秒', $delay);
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

    /**
     * 处理日志
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
