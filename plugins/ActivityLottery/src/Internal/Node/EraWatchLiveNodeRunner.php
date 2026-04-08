<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\WatchLiveGateway;

final class EraWatchLiveNodeRunner implements NodeRunnerInterface
{
    public function __construct(
        private readonly WatchLiveGateway $watchGateway = new WatchLiveGateway(),
    ) {
    }

    public function type(): string
    {
        return 'era_task_watch_live';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if ($taskView->taskId() === '' || $task === null) {
            return new ActivityNodeResult(true, '直播任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $state = $taskView->taskRuntime();
        $progress = $taskView->taskProgress();
        $serverWatchSeconds = EraWatchProgress::currentSeconds($task, $progress);
        $localWatchSeconds = max(max(0, (int)($state['local_watch_seconds'] ?? 0)), $serverWatchSeconds);
        if ($localWatchSeconds > 0) {
            $state['local_watch_seconds'] = $localWatchSeconds;
        }

        if ($taskView->resolvedTaskStatus() === 3) {
            unset($state['live_session']);
            return new ActivityNodeResult(true, '直播观看任务完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
                'context_patch' => $taskView->replaceTaskRuntime($state),
            ], $now);
        }

        $session = is_array($state['live_session'] ?? null) ? $state['live_session'] : null;
        if ($session === null) {
            $session = $this->watchGateway->start($task->targetRoomIds(), $task->targetAreaId(), $task->targetParentAreaId());
            if ($session === null) {
                return new ActivityNodeResult(true, '当前没有可观看的直播间，按完成处理', [
                    'node_status' => ActivityNodeStatus::SUCCEEDED,
                    'context_patch' => $taskView->replaceTaskRuntime($state),
                ], $now);
            }

            $waitSeconds = max(30, (int)($session['heartbeat_interval'] ?? 60));
            $nextState = array_replace($state, [
                'live_session' => $session,
            ]);

            return new ActivityNodeResult(true, '直播观看已启动', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + $waitSeconds,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        $nextSession = $this->watchGateway->heartbeat($session);
        if ($nextSession === []) {
            return new ActivityNodeResult(false, '直播观看心跳失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $elapsedSeconds = max(1, (int)($nextSession['_debug_elapsed_seconds'] ?? $nextSession['heartbeat_interval'] ?? 60));
        $localWatchSeconds = max(max(0, (int)($state['local_watch_seconds'] ?? 0)), $serverWatchSeconds) + $elapsedSeconds;
        $nextState = array_replace($state, [
            'live_session' => $nextSession,
            'local_watch_seconds' => $localWatchSeconds,
        ]);

        if (EraWatchProgress::targetSeconds($task, $progress, $localWatchSeconds) > 0) {
            $waitSeconds = max(30, (int)($nextSession['heartbeat_interval'] ?? 60));
            return new ActivityNodeResult(true, '直播观看继续推进', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + $waitSeconds,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        unset($nextState['live_session']);
        return new ActivityNodeResult(true, '直播观看任务完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $taskView->replaceTaskRuntime($nextState),
        ], $now);
    }
}

