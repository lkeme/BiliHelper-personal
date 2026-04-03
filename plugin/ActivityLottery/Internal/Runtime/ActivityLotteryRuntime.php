<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Runtime;

use Bhp\Log\Log;
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
use Bhp\Plugin\ActivityLottery\Internal\Node\EraWatchProgress;
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
use Bhp\Plugin\ActivityLottery\Internal\Node\ResolvedActivityView;
use Bhp\Plugin\ActivityLottery\Internal\Node\ResolvedEraTaskView;
use Bhp\Plugin\ActivityLottery\Internal\Node\ValidateActivityNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageSnapshot;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraTaskSnapshot;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowBudget;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowPool;
use Bhp\Scheduler\TaskResult;
use RuntimeException;

final class ActivityLotteryRuntime
{
    /** @var array<string, NodeRunnerInterface> */
    private array $runnerMap = [];
    private \Closure $logger;
    /** @var array<string, array{fingerprint: string, logged_at: int}> */
    private array $waitingLogState = [];

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
        ?callable $logger = null,
    ) {
        $this->logger = $logger !== null
            ? \Closure::fromCallable($logger)
            : static function (string $level, string $message, array $context = []): void {
                $context = array_replace(['caller' => 'ActivityLottery'], $context);
                match (strtolower(trim($level))) {
                    'warning' => Log::warning($message, $context),
                    'debug' => Log::debug($message, $context),
                    default => Log::info($message, $context),
                };
            };
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
        $bizDate = $this->bizDate();
        $this->log('debug', 'ActivityLottery 开始执行本轮 tick', [
            'event' => 'tick.start',
            'biz_date' => $bizDate,
            'timestamp' => $now,
        ]);
        if (!$this->window->contains($now)) {
            $delay = $this->secondsUntilWindowStart($now);
            $this->log('info', 'ActivityLottery 当前不在运行窗口内，跳过本轮', [
                'event' => 'tick.outside_window',
                'biz_date' => $bizDate,
                'next_delay_seconds' => $delay,
            ]);

            return TaskResult::after($delay);
        }

        $flows = [];
        foreach ($this->flowStore->load($bizDate) as $flow) {
            $flows[$flow->id()] = $flow;
        }

        $catalog = $this->catalogLoader->load();
        $this->log('debug', 'ActivityLottery 目录加载完成', [
            'event' => 'catalog.loaded',
            'biz_date' => $bizDate,
            'catalog_count' => count($catalog),
            'existing_flow_count' => count($flows),
        ]);

        $newFlowCount = 0;
        foreach ($catalog as $item) {
            $planned = $this->planner->plan($item, null, $bizDate);
            if (!isset($flows[$planned->id()])) {
                $flows[$planned->id()] = $planned;
                $newFlowCount++;
            }
        }

        $tickStartedAtMs = (int)round(microtime(true) * 1000);
        $pickedFlows = $this->flowPool->pick(array_values($flows), $now, $tickStartedAtMs);
        $this->log('debug', 'ActivityLottery 本轮调度完成 flow 选取', [
            'event' => 'tick.pick',
            'biz_date' => $bizDate,
            'flow_count' => count($flows),
            'new_flow_count' => $newFlowCount,
            'picked_flow_count' => count($pickedFlows),
        ]);
        foreach ($pickedFlows as $flow) {
            $startedAt = microtime(true);
            $currentNode = $flow->nodes()[$flow->currentNodeIndex()];
            [$executeMessage, $executeContext] = $this->buildNodeExecuteLog($flow, $currentNode);
            $executeLogContext = array_replace([
                'event' => 'node.execute',
                'biz_date' => $bizDate,
                'flow_id' => $flow->id(),
                'node_type' => $currentNode->type(),
                'node_index' => $flow->currentNodeIndex(),
            ], $executeContext);
            if ($this->shouldEmitLifecycleLog(
                'node.execute',
                $flow->id(),
                $currentNode->type(),
                $currentNode->status(),
                $executeLogContext,
                $now,
            )) {
                $this->log('info', $executeMessage, $executeLogContext);
            }
            $updated = $this->executeFlow($flow, $now);
            $flows[$updated->id()] = $updated;
            $this->flowPool->noteStepExecuted($tickStartedAtMs, $flow->id(), (microtime(true) - $startedAt) * 1000);
            $executedNodeIndex = min($flow->currentNodeIndex(), count($updated->nodes()) - 1);
            $executedNode = $updated->nodes()[$executedNodeIndex];
            $updatedNodeIndex = min($updated->currentNodeIndex(), count($updated->nodes()) - 1);
            $updatedNode = $updated->nodes()[$updatedNodeIndex];
            $result = $executedNode->result();
            [$resultMessage, $resultContext] = $this->buildNodeResultLog($flow, $currentNode, $updated, $executedNode);
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
            if ($this->shouldEmitLifecycleLog(
                'node.result',
                $updated->id(),
                $currentNode->type(),
                $executedNode->status(),
                $resultLogContext,
                $now,
            )) {
                $this->log('info', $resultMessage, $resultLogContext);
            }
            [$summaryMessage, $summaryContext] = $this->buildFlowSummaryLog($flow, $currentNode, $updated, $executedNode);
            if ($summaryMessage !== '' && $this->shouldEmitLifecycleLog(
                'flow.summary',
                $updated->id(),
                $currentNode->type(),
                $executedNode->status(),
                $summaryContext,
                $now,
            )) {
                $this->log('info', $summaryMessage, array_replace([
                    'event' => 'flow.summary',
                    'biz_date' => $bizDate,
                    'flow_id' => $updated->id(),
                    'node_type' => $currentNode->type(),
                    'node_status' => $executedNode->status(),
                    'flow_status' => $updated->status(),
                    'current_node_index' => $updated->currentNodeIndex(),
                ], $summaryContext));
            }
        }

        $this->flowStore->save(array_values($flows));

        $delay = $this->resolveNextDelaySeconds(array_values($flows), $now);
        $this->log('debug', 'ActivityLottery 本轮执行完成', [
            'event' => 'tick.finish',
            'biz_date' => $bizDate,
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

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildNodeExecuteLog(ActivityFlow $flow, \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $node): array
    {
        $context = $this->buildNodeBusinessContext($flow, $node);
        $activityTitle = $context['activity_title'] ?? '未命名活动';
        $taskName = trim((string)($context['task_name'] ?? ''));
        $label = $taskName !== '' ? sprintf('任务「%s」', $taskName) : sprintf('节点「%s」', $this->nodeLabel($node->type()));
        $suffix = $this->buildNodeExecuteSuffix($context, $node->type());

        return [
            sprintf('活动「%s」开始执行%s%s', $activityTitle, $label, $suffix),
            $context,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildNodeResultLog(
        ActivityFlow $beforeFlow,
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $beforeNode,
        ActivityFlow $afterFlow,
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
    ): array {
        $context = $this->buildNodeBusinessContext($beforeFlow, $beforeNode, $afterFlow);
        $activityTitle = $context['activity_title'] ?? '未命名活动';
        $taskName = trim((string)($context['task_name'] ?? ''));
        $label = $taskName !== '' ? sprintf('任务「%s」', $taskName) : sprintf('节点「%s」', $this->nodeLabel($beforeNode->type()));
        $message = $this->buildDetailedNodeResultMessage($beforeNode->type(), $afterNode, $context);

        return [
            sprintf('活动「%s」%s结果: %s', $activityTitle, $label, $message),
            $context,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildFlowSummaryLog(
        ActivityFlow $beforeFlow,
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $beforeNode,
        ActivityFlow $afterFlow,
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
    ): array {
        $context = $this->buildNodeBusinessContext($beforeFlow, $beforeNode, $afterFlow);
        $activityTitle = $context['activity_title'] ?? '未命名活动';
        $summary = $this->buildFlowSummaryMessage($beforeNode->type(), $afterNode, $context);
        if ($summary === '') {
            return ['', $context];
        }

        return [
            sprintf('活动「%s」当前阶段：%s', $activityTitle, $summary),
            $context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNodeBusinessContext(
        ActivityFlow $flow,
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $node,
        ?ActivityFlow $afterFlow = null,
    ): array {
        $activity = ResolvedActivityView::fromFlow($flow)->toActivityArray();
        $context = [
            'activity_title' => trim((string)($activity['title'] ?? '')),
            'activity_id' => trim((string)($activity['activity_id'] ?? '')),
        ];
        $stateSource = $afterFlow ?? $flow;
        $stateContext = $stateSource->context()->toArray();
        $context['wait_delay_seconds'] = $this->resolveWaitDelaySeconds($stateSource);
        $context['draw_times_remaining'] = max(0, (int)($stateContext['draw_times_remaining'] ?? 0));
        $lastDraw = $stateContext['last_draw_result'] ?? null;
        if (is_array($lastDraw)) {
            $context['last_draw_gift_name'] = trim((string)($lastDraw['gift_name'] ?? ''));
            $context['last_draw_gift_id'] = (int)($lastDraw['gift_id'] ?? 0);
        }
        $drawSummary = $stateContext['draw_summary'] ?? null;
        if (is_array($drawSummary)) {
            $context['draw_total_count'] = max(0, (int)($drawSummary['total_count'] ?? 0));
            $context['draw_win_count'] = max(0, (int)($drawSummary['win_count'] ?? 0));
            $context['draw_win_names'] = array_values(array_filter(array_map(
                static fn (mixed $win): string => is_array($win) ? trim((string)($win['gift_name'] ?? '')) : '',
                is_array($drawSummary['wins'] ?? null) ? $drawSummary['wins'] : [],
            )));
        }

        $taskId = trim((string)($node->payload()['task_id'] ?? ''));
        if ($taskId === '') {
            return $context;
        }

        $context['task_id'] = $taskId;
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if ($task !== null) {
            $context['task_name'] = $task->taskName();
            $context['display_target_seconds'] = $this->resolveDisplayTargetSeconds($task);
        }

        $runtimeMap = $stateContext['era_task_runtime'] ?? [];
        $state = is_array($runtimeMap[$taskId] ?? null) ? $runtimeMap[$taskId] : [];
        $context['local_watch_seconds'] = max(0, (int)($state['local_watch_seconds'] ?? 0));

        $archive = $this->resolveCurrentArchive($task, $state);
        if (is_array($archive)) {
            $context['archive_aid'] = trim((string)($archive['aid'] ?? ''));
            $context['archive_bvid'] = trim((string)($archive['bvid'] ?? ''));
        }
        if (is_array($state['live_session'] ?? null)) {
            $liveSession = $state['live_session'];
            $context['room_id'] = (int)($liveSession['room_id'] ?? 0);
            $context['heartbeat_interval'] = max(0, (int)($liveSession['heartbeat_interval'] ?? 0));
        }
        if ($task !== null) {
            $targetUids = $task->targetUids();
            $completedCount = min(count($targetUids), max(0, (int)($state['follow_target_index'] ?? 0)));
            $context['follow_total_count'] = count($targetUids);
            $context['follow_completed_count'] = $completedCount;
            if (isset($targetUids[$completedCount])) {
                $context['target_uid'] = (string)$targetUids[$completedCount];
            }
        }
        return $context;
    }

    private function nodeLabel(string $nodeType): string
    {
        return match ($nodeType) {
            'load_activity_snapshot' => '加载活动页',
            'validate_activity_window' => '校验活动窗口',
            'parse_era_page' => '解析活动任务页',
            'refresh_draw_times' => '刷新抽奖次数',
            'execute_draw' => '执行抽奖',
            'record_draw_result' => '记录抽奖结果',
            'notify_draw_result' => '通知抽奖结果',
            'final_claim_reward' => '收尾领奖',
            'finalize_flow' => '收尾活动流',
            'era_task_follow' => '关注任务',
            'era_task_share' => '分享任务',
            'era_task_watch_video_fixed', 'era_task_watch_video_topic' => '观看视频任务',
            'era_task_watch_live' => '观看直播任务',
            'era_task_claim_reward' => '领奖任务',
            'era_task_skipped' => '跳过任务',
            default => $nodeType,
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildNodeExecuteSuffix(array $context, string $nodeType): string
    {
        return match ($nodeType) {
            'era_task_follow' => ($context['target_uid'] ?? '') !== ''
                ? sprintf(' [目标UID=%s]', (string)$context['target_uid'])
                : '',
            'era_task_watch_video_fixed', 'era_task_watch_video_topic' => ($archiveLabel = $this->archiveLabel($context)) !== ''
                ? sprintf(' [稿件=%s]', $archiveLabel)
                : '',
            'era_task_watch_live' => (int)($context['room_id'] ?? 0) > 0
                ? sprintf(' [房间=%d]', (int)$context['room_id'])
                : '',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildDetailedNodeResultMessage(
        string $nodeType,
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $fallback = trim((string)($afterNode->result()?->message() ?? '执行结束'));
        $delay = $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0));

        return match ($nodeType) {
            'era_task_follow' => $this->buildFollowResultMessage($afterNode, $context, $fallback, $delay),
            'era_task_watch_video_fixed', 'era_task_watch_video_topic' => $this->buildWatchVideoResultMessage($afterNode, $context, $fallback, $delay),
            'era_task_watch_live' => $this->buildWatchLiveResultMessage($afterNode, $context, $fallback, $delay),
            'refresh_draw_times' => $this->buildRefreshDrawResultMessage($afterNode, $context, $fallback),
            'execute_draw' => $this->buildExecuteDrawResultMessage($afterNode, $context, $fallback, $delay),
            'record_draw_result' => $this->buildRecordDrawResultMessage($afterNode, $context, $fallback),
            default => $fallback,
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildFlowSummaryMessage(
        string $nodeType,
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        return match ($nodeType) {
            'era_task_follow' => $this->buildFollowSummaryMessage($afterNode, $context),
            'era_task_watch_video_fixed', 'era_task_watch_video_topic' => $this->buildWatchVideoSummaryMessage($afterNode, $context),
            'era_task_watch_live' => $this->buildWatchLiveSummaryMessage($afterNode, $context),
            'refresh_draw_times' => $this->buildRefreshDrawSummaryMessage($afterNode, $context),
            'execute_draw' => $this->buildExecuteDrawSummaryMessage($afterNode, $context),
            'record_draw_result' => $this->buildRecordDrawSummaryMessage($afterNode, $context),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildFollowResultMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
        string $delay,
    ): string {
        $completed = max(0, (int)($context['follow_completed_count'] ?? 0));
        $total = max(0, (int)($context['follow_total_count'] ?? 0));
        $nextTargetUid = trim((string)($context['target_uid'] ?? ''));

        if ($afterNode->status() === ActivityNodeStatus::WAITING && $total > 0) {
            $suffix = $nextTargetUid !== '' ? sprintf('，下一目标 UID=%s', $nextTargetUid) : '';
            return sprintf('已完成 %d/%d%s%s', $completed, $total, $suffix, $delay);
        }

        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED && $total > 0) {
            return sprintf('%s，已完成 %d/%d', $fallback, $completed, $total);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildWatchVideoResultMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
        string $delay,
    ): string {
        $currentSeconds = max(0, (int)($context['local_watch_seconds'] ?? 0));
        $targetSeconds = max(0, (int)($context['display_target_seconds'] ?? 0));
        $progress = $targetSeconds > 0
            ? sprintf('%d/%d 秒', $currentSeconds, $targetSeconds)
            : sprintf('%d 秒', $currentSeconds);
        $archiveLabel = $this->archiveLabel($context);
        $archivePrefix = $archiveLabel !== '' ? sprintf('稿件 %s，', $archiveLabel) : '';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            if (str_contains($fallback, '视频观看继续推进')) {
                return sprintf('恢复推进，%s当前累计 %s%s', $archivePrefix, $progress, $delay);
            }
            if (str_contains($fallback, '视频观看已启动')) {
                if ($currentSeconds > 0) {
                    return sprintf('重新建链，%s当前累计 %s%s', $archivePrefix, $progress, $delay);
                }

                return sprintf('首次启动观看，%s当前累计 %s%s', $archivePrefix, $progress, $delay);
            }
            if ($archivePrefix === '' && $currentSeconds <= 0) {
                return $fallback . $delay;
            }
            return sprintf('%s当前累计 %s%s', $archivePrefix, $progress, $delay);
        }

        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('%s，累计 %s', $fallback, $progress);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildWatchLiveResultMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
        string $delay,
    ): string {
        $roomId = max(0, (int)($context['room_id'] ?? 0));
        $currentSeconds = max(0, (int)($context['local_watch_seconds'] ?? 0));
        $targetSeconds = max(0, (int)($context['display_target_seconds'] ?? 0));
        $progress = $targetSeconds > 0
            ? sprintf('%d/%d 秒', $currentSeconds, $targetSeconds)
            : sprintf('%d 秒', $currentSeconds);
        $roomPrefix = $roomId > 0 ? sprintf('房间 %d，', $roomId) : '';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            if (str_contains($fallback, '直播观看继续推进')) {
                return sprintf('恢复推进，%s当前累计 %s%s', $roomPrefix, $progress, $delay);
            }
            if (str_contains($fallback, '直播观看已启动')) {
                if ($currentSeconds > 0) {
                    return sprintf('重新接入直播间，%s当前累计 %s%s', $roomPrefix, $progress, $delay);
                }

                return sprintf('首次接入直播间，%s当前累计 %s%s', $roomPrefix, $progress, $delay);
            }
            if ($roomPrefix === '' && $currentSeconds <= 0) {
                return $fallback . $delay;
            }
            return sprintf('%s当前累计 %s%s', $roomPrefix, $progress, $delay);
        }

        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('%s，累计 %s', $fallback, $progress);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRefreshDrawResultMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
    ): string {
        $remaining = max(0, (int)($context['draw_times_remaining'] ?? 0));
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('当前可抽 %d 次', $remaining);
        }
        if ($afterNode->status() === ActivityNodeStatus::SKIPPED) {
            return '当前无可用抽奖次数';
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildExecuteDrawResultMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
        string $delay,
    ): string {
        $remaining = max(0, (int)($context['draw_times_remaining'] ?? 0));
        $resultName = trim((string)($context['last_draw_gift_name'] ?? ''));
        $resultLabel = $resultName !== '' ? $resultName : '未知结果';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            return sprintf('本次结果：%s，剩余 %d 次%s', $resultLabel, $remaining, $delay);
        }
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('本次结果：%s，剩余 %d 次', $resultLabel, $remaining);
        }
        if ($afterNode->status() === ActivityNodeStatus::SKIPPED) {
            return '抽奖次数已耗尽';
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRecordDrawResultMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
        string $fallback,
    ): string {
        if ($afterNode->status() !== ActivityNodeStatus::SUCCEEDED) {
            return $fallback;
        }

        $total = max(0, (int)($context['draw_total_count'] ?? 0));
        $winCount = max(0, (int)($context['draw_win_count'] ?? 0));
        $wins = is_array($context['draw_win_names'] ?? null) ? $context['draw_win_names'] : [];
        if ($winCount <= 0) {
            return sprintf('累计抽奖 %d 次，未命中', $total);
        }

        return sprintf('累计抽奖 %d 次，命中 %d 次，奖品：%s', $total, $winCount, implode(' / ', $wins));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildFollowSummaryMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $completed = max(0, (int)($context['follow_completed_count'] ?? 0));
        $total = max(0, (int)($context['follow_total_count'] ?? 0));
        if ($afterNode->status() === ActivityNodeStatus::WAITING && $total > 0) {
            $nextTargetUid = trim((string)($context['target_uid'] ?? ''));
            $suffix = $nextTargetUid !== '' ? sprintf('，下一目标 UID=%s', $nextTargetUid) : '';
            return sprintf('关注任务，已完成 %d/%d%s%s', $completed, $total, $suffix, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
        }

        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED && $total > 0) {
            return sprintf('关注任务，已完成 %d/%d', $completed, $total);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildWatchVideoSummaryMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $currentSeconds = max(0, (int)($context['local_watch_seconds'] ?? 0));
        $targetSeconds = max(0, (int)($context['display_target_seconds'] ?? 0));
        $progress = $targetSeconds > 0 ? sprintf('%d/%d 秒', $currentSeconds, $targetSeconds) : sprintf('%d 秒', $currentSeconds);
        $archiveLabel = $this->archiveLabel($context);
        $prefix = $archiveLabel !== '' ? sprintf('观看视频，稿件 %s，', $archiveLabel) : '观看视频，';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            return sprintf('%s当前累计 %s%s', $prefix, $progress, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
        }
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('%s累计 %s', $prefix, $progress);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildWatchLiveSummaryMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $currentSeconds = max(0, (int)($context['local_watch_seconds'] ?? 0));
        $targetSeconds = max(0, (int)($context['display_target_seconds'] ?? 0));
        $progress = $targetSeconds > 0 ? sprintf('%d/%d 秒', $currentSeconds, $targetSeconds) : sprintf('%d 秒', $currentSeconds);
        $roomId = max(0, (int)($context['room_id'] ?? 0));
        $prefix = $roomId > 0 ? sprintf('观看直播，房间 %d，', $roomId) : '观看直播，';

        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            return sprintf('%s当前累计 %s%s', $prefix, $progress, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
        }
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('%s累计 %s', $prefix, $progress);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRefreshDrawSummaryMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $remaining = max(0, (int)($context['draw_times_remaining'] ?? 0));
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('抽奖阶段，当前可抽 %d 次', $remaining);
        }
        if ($afterNode->status() === ActivityNodeStatus::SKIPPED) {
            return '抽奖阶段，当前无可用抽奖次数';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildExecuteDrawSummaryMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        $remaining = max(0, (int)($context['draw_times_remaining'] ?? 0));
        $resultName = trim((string)($context['last_draw_gift_name'] ?? ''));
        $resultLabel = $resultName !== '' ? $resultName : '未知结果';
        if ($afterNode->status() === ActivityNodeStatus::WAITING) {
            return sprintf('抽奖阶段，本次结果：%s，剩余 %d 次%s', $resultLabel, $remaining, $this->formatDelaySuffix((int)($context['wait_delay_seconds'] ?? 0)));
        }
        if ($afterNode->status() === ActivityNodeStatus::SUCCEEDED) {
            return sprintf('抽奖阶段，本次结果：%s，剩余 %d 次', $resultLabel, $remaining);
        }
        if ($afterNode->status() === ActivityNodeStatus::SKIPPED) {
            return '抽奖阶段，抽奖次数已耗尽';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildRecordDrawSummaryMessage(
        \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode $afterNode,
        array $context,
    ): string {
        if ($afterNode->status() !== ActivityNodeStatus::SUCCEEDED) {
            return '';
        }

        $total = max(0, (int)($context['draw_total_count'] ?? 0));
        $winCount = max(0, (int)($context['draw_win_count'] ?? 0));
        $wins = is_array($context['draw_win_names'] ?? null) ? $context['draw_win_names'] : [];
        if ($winCount <= 0) {
            return sprintf('抽奖汇总，累计抽奖 %d 次，未命中', $total);
        }

        return sprintf('抽奖汇总，累计抽奖 %d 次，命中 %d 次，奖品：%s', $total, $winCount, implode(' / ', $wins));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function archiveLabel(array $context): string
    {
        $bvid = trim((string)($context['archive_bvid'] ?? ''));
        if ($bvid !== '') {
            return $bvid;
        }

        $aid = trim((string)($context['archive_aid'] ?? ''));
        return $aid !== '' ? 'aid=' . $aid : '';
    }

    private function resolveWaitDelaySeconds(ActivityFlow $flow): int
    {
        $nextRunAt = $flow->nextRunAt();
        if ($nextRunAt <= 0) {
            return 0;
        }

        return max(0, $nextRunAt - $flow->updatedAt());
    }

    private function formatDelaySuffix(int $delaySeconds): string
    {
        if ($delaySeconds <= 0) {
            return '';
        }

        if ($delaySeconds % 3600 === 0 && $delaySeconds >= 3600) {
            return sprintf('，%d 小时后继续', (int)($delaySeconds / 3600));
        }
        if ($delaySeconds % 60 === 0 && $delaySeconds >= 60) {
            return sprintf('，%d 分钟后继续', (int)($delaySeconds / 60));
        }

        return sprintf('，%d 秒后继续', $delaySeconds);
    }

    private function resolveDisplayTargetSeconds(?EraTaskSnapshot $task = null): int
    {
        if ($task === null) {
            return 0;
        }

        $thresholds = EraWatchProgress::thresholds($task);
        if ($thresholds !== []) {
            return max($thresholds);
        }

        return max(0, $task->requiredWatchSeconds());
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    private function resolveCurrentArchive(?EraTaskSnapshot $task, array $state): ?array
    {
        if (is_array($state['watch_video_archive'] ?? null)) {
            return $state['watch_video_archive'];
        }
        if (is_array($state['topic_archives'] ?? null)) {
            $archives = $state['topic_archives'];
            $index = max(0, (int)($state['topic_archive_index'] ?? 0));
            if (isset($archives[$index]) && is_array($archives[$index])) {
                return $archives[$index];
            }
        }
        if ($task !== null) {
            $archives = $task->targetArchives();
            if ($archives !== []) {
                $index = max(0, (int)($state['fixed_archive_index'] ?? 0));
                if (isset($archives[$index]) && is_array($archives[$index])) {
                    return $archives[$index];
                }
                if (isset($archives[0]) && is_array($archives[0])) {
                    return $archives[0];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function shouldEmitLifecycleLog(
        string $event,
        string $flowId,
        string $nodeType,
        string $nodeStatus,
        array $context,
        int $now,
    ): bool {
        if ($nodeStatus !== ActivityNodeStatus::WAITING) {
            return true;
        }

        $fingerprint = sha1(json_encode([
            'event' => $event,
            'node_type' => $nodeType,
            'task_id' => $context['task_id'] ?? '',
            'task_name' => $context['task_name'] ?? '',
            'target_uid' => $context['target_uid'] ?? '',
            'follow_completed_count' => $context['follow_completed_count'] ?? 0,
            'follow_total_count' => $context['follow_total_count'] ?? 0,
            'archive_bvid' => $context['archive_bvid'] ?? '',
            'archive_aid' => $context['archive_aid'] ?? '',
            'room_id' => $context['room_id'] ?? 0,
            'local_watch_seconds' => $context['local_watch_seconds'] ?? 0,
            'display_target_seconds' => $context['display_target_seconds'] ?? 0,
            'draw_times_remaining' => $context['draw_times_remaining'] ?? 0,
            'last_draw_gift_name' => $context['last_draw_gift_name'] ?? '',
            'wait_delay_seconds' => $context['wait_delay_seconds'] ?? 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $key = $flowId . '|' . $event . '|' . $nodeType;
        $previous = $this->waitingLogState[$key] ?? null;
        if (is_array($previous)) {
            $sameFingerprint = ($previous['fingerprint'] ?? '') === $fingerprint;
            $withinCooldown = ($now - (int)($previous['logged_at'] ?? 0)) < 60;
            if ($sameFingerprint && $withinCooldown) {
                return false;
            }
        }

        $this->waitingLogState[$key] = [
            'fingerprint' => $fingerprint,
            'logged_at' => $now,
        ];

        return true;
    }
}
