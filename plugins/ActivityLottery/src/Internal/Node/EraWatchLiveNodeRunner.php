<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\WatchLiveGateway;
use Bhp\Util\Exceptions\RequestException;

final class EraWatchLiveNodeRunner implements NodeRunnerInterface
{
    private const RETRY_DELAY_SECONDS = 300;
    private const INIT_FAILURE_SWITCH_THRESHOLD = 3;
    private const INIT_PREFER_RECOMMEND_COOLDOWN_SECONDS = 1800;
    private const HEARTBEAT_FAILURE_SWITCH_THRESHOLD = 3;
    private const FAILED_ROOM_COOLDOWN_SECONDS = 1800;

    /**
     * 初始化 EraWatchLiveNodeRunner
     * @param WatchLiveGateway $watchGateway
     */
    public function __construct(
        private readonly WatchLiveGateway $watchGateway,
    ) {
    }

    /**
     * 获取类型标识
     * @return string
     */
    public function type(): string
    {
        return 'era_task_watch_live';
    }

    /**
     * 启动执行流程
     * @param ActivityFlow $flow
     * @param ActivityNode $node
     * @param int $now
     * @return ActivityNodeResult
     */
    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if ($taskView->taskId() === '' || $task === null) {
            return new ActivityNodeResult(true, '直播任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $state = $this->pruneState($taskView->taskRuntime(), $now);
        $progress = $taskView->taskProgress();
        $serverWatchSeconds = EraWatchProgress::currentSeconds($task, $progress);
        $localWatchSeconds = max(max(0, (int)($state['local_watch_seconds'] ?? 0)), $serverWatchSeconds);
        if ($localWatchSeconds > 0) {
            $state['local_watch_seconds'] = $localWatchSeconds;
        }

        if ($taskView->resolvedTaskStatus() === 3) {
            unset(
                $state['live_session'],
                $state['live_failure_count'],
                $state['live_failed_room_ids'],
                $state['live_init_failure_count'],
                $state['live_init_strategy'],
                $state['live_init_strategy_until'],
            );
            return new ActivityNodeResult(true, '直播观看任务完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
                'context_patch' => $taskView->replaceTaskRuntime($state),
            ], $now);
        }

        $session = is_array($state['live_session'] ?? null) ? $state['live_session'] : null;
        $excludedRoomIds = $this->excludedRoomIds($state);
        if ($session === null) {
            $preferRecommendOnly = $this->preferRecommendOnly($state, $now);
            try {
                $session = $this->watchGateway->start(
                    $task->targetRoomIds(),
                    $task->targetAreaId(),
                    $task->targetParentAreaId(),
                    $excludedRoomIds,
                    $preferRecommendOnly,
                );
            } catch (RequestException $exception) {
                $failureState = $this->applyInitFailure($state, $now);
                $message = '直播观看初始化失败: ' . $exception->getMessage();
                if (($failureState['switched'] ?? false) === true) {
                    $message .= '，已切换推荐兜底';
                }

                return new ActivityNodeResult(false, $message, [
                    'node_status' => ActivityNodeStatus::WAITING,
                    'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                    'context_patch' => $taskView->replaceTaskRuntime($failureState['state']),
                ], $now);
            }

            if ($session === null) {
                if ($excludedRoomIds !== []) {
                    return new ActivityNodeResult(false, '当前候选直播间均在冷却中，稍后重试', [
                        'node_status' => ActivityNodeStatus::WAITING,
                        'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                        'context_patch' => $taskView->replaceTaskRuntime($state),
                    ], $now);
                }

                if ($preferRecommendOnly) {
                    return new ActivityNodeResult(false, '推荐兜底暂未命中可用直播间，稍后重试', [
                        'node_status' => ActivityNodeStatus::WAITING,
                        'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                        'context_patch' => $taskView->replaceTaskRuntime($state),
                    ], $now);
                }

                return new ActivityNodeResult(true, '当前没有可观看的直播间，按完成处理', [
                    'node_status' => ActivityNodeStatus::SUCCEEDED,
                    'context_patch' => $taskView->replaceTaskRuntime($state),
                ], $now);
            }

            $session['room_pick_mode'] = $preferRecommendOnly ? 'recommend_only' : 'default';
            $waitSeconds = max(30, (int)($session['heartbeat_interval'] ?? 60));
            $nextState = array_replace($state, [
                'live_session' => $session,
                'live_failure_count' => 0,
                'live_init_failure_count' => 0,
            ]);
            unset(
                $nextState['live_init_strategy'],
                $nextState['live_init_strategy_until']
            );

            return new ActivityNodeResult(true, '直播观看已启动', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + $waitSeconds,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        try {
            $nextSession = $this->watchGateway->heartbeat($session);
        } catch (RequestException $exception) {
            $failureState = $this->applyHeartbeatFailure($state, $session, $now);
            $message = '直播观看心跳失败: ' . $exception->getMessage();
            if (($failureState['switched'] ?? false) === true) {
                $message .= '，已切换候选直播间';
            }

            return new ActivityNodeResult(false, $message, [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                'context_patch' => $taskView->replaceTaskRuntime($failureState['state']),
            ], $now);
        }

        if ($nextSession === []) {
            $failureState = $this->applyHeartbeatFailure($state, $session, $now);
            $message = '直播观看心跳失败';
            if (($failureState['switched'] ?? false) === true) {
                $message .= '，已切换候选直播间';
            }

            return new ActivityNodeResult(false, $message, [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                'context_patch' => $taskView->replaceTaskRuntime($failureState['state']),
            ], $now);
        }

        $elapsedSeconds = max(1, (int)($nextSession['_debug_elapsed_seconds'] ?? $nextSession['heartbeat_interval'] ?? 60));
        $localWatchSeconds = max(max(0, (int)($state['local_watch_seconds'] ?? 0)), $serverWatchSeconds) + $elapsedSeconds;
        $nextState = array_replace($state, [
            'live_session' => $nextSession,
            'local_watch_seconds' => $localWatchSeconds,
            'live_failure_count' => 0,
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

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function pruneState(array $state, int $now): array
    {
        $rawCooldowns = is_array($state['live_failed_room_ids'] ?? null) ? $state['live_failed_room_ids'] : [];
        $cooldowns = [];
        foreach ($rawCooldowns as $roomId => $expireAt) {
            $normalizedRoomId = (int)$roomId;
            $normalizedExpireAt = (int)$expireAt;
            if ($normalizedRoomId <= 0 || $normalizedExpireAt <= $now) {
                continue;
            }

            $cooldowns[(string)$normalizedRoomId] = $normalizedExpireAt;
        }

        if ($cooldowns === []) {
            unset($state['live_failed_room_ids']);
        } else {
            $state['live_failed_room_ids'] = $cooldowns;
        }

        if (!$this->preferRecommendOnly($state, $now)) {
            unset($state['live_init_strategy'], $state['live_init_strategy_until']);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return int[]
     */
    private function excludedRoomIds(array $state): array
    {
        $rawCooldowns = is_array($state['live_failed_room_ids'] ?? null) ? $state['live_failed_room_ids'] : [];
        $roomIds = [];
        foreach ($rawCooldowns as $roomId => $expireAt) {
            if ((int)$expireAt <= 0) {
                continue;
            }

            $normalizedRoomId = (int)$roomId;
            if ($normalizedRoomId > 0) {
                $roomIds[] = $normalizedRoomId;
            }
        }

        return array_values(array_unique($roomIds));
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $session
     * @return array{state: array<string, mixed>, switched: bool}
     */
    private function applyHeartbeatFailure(array $state, array $session, int $now): array
    {
        $failureCount = max(0, (int)($state['live_failure_count'] ?? 0)) + 1;
        $nextState = $state;
        $nextState['live_failure_count'] = $failureCount;
        $roomId = max(0, (int)($session['room_id'] ?? 0));
        if ($roomId <= 0 || $failureCount < self::HEARTBEAT_FAILURE_SWITCH_THRESHOLD) {
            return [
                'state' => $nextState,
                'switched' => false,
            ];
        }

        $cooldowns = is_array($nextState['live_failed_room_ids'] ?? null) ? $nextState['live_failed_room_ids'] : [];
        $cooldowns[(string)$roomId] = $now + self::FAILED_ROOM_COOLDOWN_SECONDS;
        $nextState['live_failed_room_ids'] = $cooldowns;
        $nextState['live_failure_count'] = 0;
        unset($nextState['live_session']);

        return [
            'state' => $nextState,
            'switched' => true,
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return array{state: array<string, mixed>, switched: bool}
     */
    private function applyInitFailure(array $state, int $now): array
    {
        $failureCount = max(0, (int)($state['live_init_failure_count'] ?? 0)) + 1;
        $nextState = $state;
        $nextState['live_init_failure_count'] = $failureCount;

        if ($this->preferRecommendOnly($state, $now) || $failureCount < self::INIT_FAILURE_SWITCH_THRESHOLD) {
            return [
                'state' => $nextState,
                'switched' => false,
            ];
        }

        $nextState['live_init_failure_count'] = 0;
        $nextState['live_init_strategy'] = 'recommend_only';
        $nextState['live_init_strategy_until'] = $now + self::INIT_PREFER_RECOMMEND_COOLDOWN_SECONDS;

        return [
            'state' => $nextState,
            'switched' => true,
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function preferRecommendOnly(array $state, int $now): bool
    {
        if (trim((string)($state['live_init_strategy'] ?? '')) !== 'recommend_only') {
            return false;
        }

        return (int)($state['live_init_strategy_until'] ?? 0) > $now;
    }
}

