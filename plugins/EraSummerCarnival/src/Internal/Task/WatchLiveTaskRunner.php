<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

use Bhp\Api\Api\X\ActivityComponents\ApiEvaOperation;
use Bhp\Api\XLive\WebRoom\V1\Index\ApiIndex;
use Bhp\Automation\Watch\LiveWatchService;
use Bhp\Automation\Watch\LiveWatchSession;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Follow\FollowStateResolver;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\State\CarnivalStateStore;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\TaskStatus;
use RuntimeException;

/**
 * 板块内直播间观看五分钟（R4）。
 *
 * 关键约束：心跳间隔为 max(30, heartbeat_interval)，累计 5 分钟约需 10 次心跳 ≈ 5 分钟墙钟时间，
 * 无法在单 tick 内跑满。因此会话必须跨 tick 持久化 —— 每 tick 只发一次心跳后立即返回，
 * 由编排层按 heartbeat_interval 安排下次唤醒。
 *
 * 房间来源：eva_operation/list 只给 area 名称，没有 area_id 数字，
 * 而 LiveWatchService::start() 的 boundary 断言要求 ruid / parent_area_id / area_id 全部 > 0，
 * 故必须补一步 ApiIndex::getInfoByRoom() 取权威房间信息（同时复核 live_status）。
 */
final class WatchLiveTaskRunner implements TaskRunnerInterface
{
    private const NO_ROOM_BACKOFF_SECONDS = [1800, 3600, 7200];
    private const OPERATION_FAILURE_RETRY_SECONDS = 900.0;
    private const SESSION_RESET_DELAY_SECONDS = 60.0;
    private const ROOM_AVAILABLE = 'available';
    private const ROOM_UNAVAILABLE = 'unavailable';
    private const ROOM_OPERATIONAL_FAILURE = 'operational_failure';

    private readonly ApiEvaOperation $apiEvaOperation;
    private readonly ApiIndex $apiIndex;
    private readonly LiveWatchService $liveWatchService;
    private readonly CarnivalStateStore $stateStore;
    private readonly FollowStateResolver $followStateResolver;
    private readonly AuthFailureClassifier $authFailureClassifier;

    /** @var \Closure(string, string, array<string, mixed>): void */
    private readonly \Closure $logger;

    /**
     * @param callable(string, string, array<string, mixed>): void $logger
     */
    public function __construct(
        ApiEvaOperation $apiEvaOperation,
        ApiIndex $apiIndex,
        LiveWatchService $liveWatchService,
        CarnivalStateStore $stateStore,
        FollowStateResolver $followStateResolver,
        callable $logger,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiEvaOperation = $apiEvaOperation;
        $this->apiIndex = $apiIndex;
        $this->liveWatchService = $liveWatchService;
        $this->stateStore = $stateStore;
        $this->followStateResolver = $followStateResolver;
        $this->logger = \Closure::fromCallable($logger);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    public function key(): string
    {
        return 'watch_live';
    }

    public function run(CarnivalContext $context): CarnivalStepResult
    {
        $snapshot = $context->snapshot;
        $taskId = $snapshot->watchLiveTaskId;
        if ($taskId === '' || $snapshot->liveSourceId === '') {
            return CarnivalStepResult::skipped('观看直播: 缺少 task_id 或 source_id');
        }

        $status = $context->taskStatus($taskId);
        if (in_array($status, [TaskStatus::CLAIMABLE, TaskStatus::FINISHED], true)) {
            $this->clearWatchState();

            return CarnivalStepResult::skipped('观看直播: 今日任务已达成');
        }

        // 服务端进度已满但 task_status 尚未翻转时同样不再观看，
        // 避免本地 watch_progress 丢失（换机、清缓存）后重复刷满一遍。
        $taskLimit = $context->taskLimit($taskId);
        if ($taskLimit > 0 && $context->taskCurrentValue($taskId) >= $taskLimit) {
            $this->clearWatchState();

            return CarnivalStepResult::skipped('观看直播: 服务端进度已满，今日无需再看');
        }

        $target = max(1, $snapshot->watchLiveTargetSeconds);
        $watched = $this->stateStore->watchedSeconds($context->bizDate);
        if ($watched >= $target) {
            $this->clearWatchState();

            return CarnivalStepResult::skipped("观看直播: 今日已累计 {$watched}s，达到目标");
        }

        $stored = $this->stateStore->watchSession();
        if ($stored === null) {
            return $this->startSession($context);
        }

        return $this->continueSession($context, $stored, $target, $watched);
    }

    /**
     * 选房并建立观看会话
     */
    private function startSession(CarnivalContext $context): CarnivalStepResult
    {
        $backoff = $this->stateStore->watchProbeBackoff();
        if ($backoff !== null && $backoff['date'] !== $context->bizDate) {
            $this->stateStore->clearWatchProbeBackoff();
            $backoff = null;
        }
        if ($backoff !== null && $context->now < $backoff['next_probe_at']) {
            $remaining = max(1, $backoff['next_probe_at'] - $context->now);

            return CarnivalStepResult::postponed(
                (float)$remaining,
                "直播间探测冷却中，{$remaining}s 后重试",
            );
        }

        $response = $this->apiEvaOperation->list(
            $context->snapshot->liveSourceId,
            50,
            1,
            $context->snapshot->activityUrl,
        );
        $this->authFailureClassifier->assertNotAuthFailure($response, '次元奇旅获取板块直播间时账号未登录');

        $code = (int)($response['code'] ?? -1);
        if ($code !== 0) {
            $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));
            $this->log('warning', '次元奇旅: 获取板块直播间失败', ['code' => $code, 'message' => $message]);

            return CarnivalStepResult::failed(
                self::OPERATION_FAILURE_RETRY_SECONDS,
                "获取板块直播间失败 {$code} -> {$message}",
            );
        }

        $list = $response['data']['list'] ?? null;
        if (!is_array($list)) {
            $this->log('warning', '次元奇旅: 板块直播间接口返回缺少合法 data.list，稍后重试', []);

            return CarnivalStepResult::failed(
                self::OPERATION_FAILURE_RETRY_SECONDS,
                '板块直播间接口返回缺少合法 data.list',
            );
        }

        // 保留既有关注提示预热；当前 FollowTaskRunner 仍会独立做权威关系查询。
        $this->followStateResolver->primeFromOperationList($list);

        $candidates = $this->pickLiveCandidates($list);
        if ($candidates === []) {
            return $this->postponeForNoRoom($context, '板块内暂无在播直播间', 0);
        }

        $operationalFailures = [];
        foreach ($candidates as $roomId) {
            $resolution = $this->resolveRoom($roomId);
            if ($resolution['outcome'] === self::ROOM_OPERATIONAL_FAILURE) {
                $operationalFailures[] = $resolution['error'];
                continue;
            }
            if ($resolution['outcome'] !== self::ROOM_AVAILABLE || $resolution['room'] === null) {
                continue;
            }
            $resolved = $resolution['room'];

            try {
                $session = $this->liveWatchService->start($resolved['room_id'], [
                    'ruid' => $resolved['ruid'],
                    'parent_area_id' => $resolved['parent_area_id'],
                    'area_id' => $resolved['area_id'],
                    'room_title' => $resolved['room_title'],
                    'room_uname' => $resolved['room_uname'],
                    'room_pick_source' => 'era_operation',
                ]);
            } catch (RuntimeException $exception) {
                $this->log('debug', '次元奇旅: 直播观看建链失败，尝试下一个房间', [
                    'room_id' => $roomId,
                    'error' => $exception->getMessage(),
                ]);
                $operationalFailures[] = "room={$roomId} start={$exception->getMessage()}";
                continue;
            }

            $this->stateStore->clearWatchProbeBackoff();
            $this->stateStore->putWatchSession($session->toArray());
            $this->log('info', '次元奇旅: 开始观看板块内直播', [
                'room_id' => $session->roomId,
                '主播' => $resolved['room_uname'],
                '心跳间隔' => $session->heartbeatInterval . 's',
            ]);

            return CarnivalStepResult::again((float)$session->heartbeatInterval, '直播观看会话已建立');
        }

        if ($operationalFailures !== []) {
            $summary = implode(' | ', array_slice($operationalFailures, 0, 3));
            $this->log('warning', '次元奇旅: 直播间探测或建链失败，900 秒后重试', [
                '候选数' => count($candidates),
                '故障数' => count($operationalFailures),
                'errors' => $summary,
            ]);

            return CarnivalStepResult::failed(
                self::OPERATION_FAILURE_RETRY_SECONDS,
                "直播间探测或建链失败: {$summary}",
            );
        }

        return $this->postponeForNoRoom($context, '候选直播间均不可用', count($candidates));
    }

    /**
     * 续跑已有会话：本 tick 只发一次心跳
     *
     * @param array<string, mixed> $stored
     */
    private function continueSession(CarnivalContext $context, array $stored, int $target, int $watched): CarnivalStepResult
    {
        try {
            $session = LiveWatchSession::fromArray($stored);
            $previousHeartbeatAt = $session->lastHeartbeatAt;
            $next = $this->liveWatchService->heartbeat($session);
        } catch (RuntimeException $exception) {
            $this->stateStore->clearWatchSession();
            $this->log('warning', '次元奇旅: 直播观看心跳失败，清空会话后重新选房', [
                'error' => $exception->getMessage(),
            ]);

            return CarnivalStepResult::postponed(self::SESSION_RESET_DELAY_SECONDS, '直播观看会话失效');
        }

        $elapsed = $previousHeartbeatAt > 0.0
            ? (int)max(1, floor($next->lastHeartbeatAt - $previousHeartbeatAt))
            : max(1, $session->heartbeatInterval);
        // 单次心跳计入的时长不应超过一个心跳周期，避免长时间空转被累计
        $elapsed = min($elapsed, max(1, $session->heartbeatInterval * 2));

        $this->stateStore->putWatchSession($next->toArray());
        $total = $this->stateStore->addWatchedSeconds($context->bizDate, $elapsed);

        if ($total >= $target) {
            $this->clearWatchState();
            $this->log('notice', '次元奇旅: 板块内直播观看时长已达标', [
                '累计' => $total . 's',
                '目标' => $target . 's',
            ]);

            return CarnivalStepResult::done("观看直播累计 {$total}s，达标");
        }

        $this->log('debug', '次元奇旅: 直播观看心跳', [
            'room_id' => $next->roomId,
            '累计' => $total . '/' . $target . 's',
        ]);

        return CarnivalStepResult::again((float)$next->heartbeatInterval, "观看直播累计 {$total}/{$target}s");
    }

    /**
     * 从运营位列表挑出在播房间，按人气降序
     *
     * @param array<int, mixed> $list
     * @return int[]
     */
    private function pickLiveCandidates(array $list): array
    {
        $rows = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }

            $live = $item['object']['live'] ?? null;
            if (!is_array($live)) {
                continue;
            }

            $roomId = (int)($live['room_id'] ?? 0);
            if ($roomId <= 0) {
                continue;
            }

            // live_status 为 1 才是在播；对未开播房间发心跳不计入进度
            if ((int)($live['live_status'] ?? 0) !== 1) {
                continue;
            }

            $rows[] = [
                'room_id' => $roomId,
                'popularity' => (int)($live['popularity_count'] ?? 0),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $right['popularity'] <=> $left['popularity']);

        return array_values(array_map(static fn (array $row): int => $row['room_id'], $rows));
    }

    /**
     * 取权威房间信息（ruid / parent_area_id / area_id），并复核在播状态
     *
     * @return array{
     *   outcome: string,
     *   room: array{room_id: int, ruid: int, parent_area_id: int, area_id: int, room_title: string, room_uname: string}|null,
     *   error: string
     * }
     */
    private function resolveRoom(int $roomId): array
    {
        if ($roomId <= 0) {
            return $this->roomResolution(self::ROOM_UNAVAILABLE);
        }

        $response = $this->apiIndex->getInfoByRoom($roomId);
        $this->authFailureClassifier->assertNotAuthFailure($response, '次元奇旅获取直播房间详情时账号未登录');

        $code = (int)($response['code'] ?? -1);
        if ($code !== 0) {
            $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));
            $this->log('debug', '次元奇旅: 直播房间信息接口异常', [
                'room_id' => $roomId,
                'code' => $code,
                'message' => $message,
            ]);

            return $this->roomResolution(
                self::ROOM_OPERATIONAL_FAILURE,
                null,
                "room={$roomId} code={$code} message={$message}",
            );
        }

        $roomInfo = is_array($response['data']['room_info'] ?? null) ? $response['data']['room_info'] : [];
        if ((int)($roomInfo['live_status'] ?? 0) !== 1) {
            return $this->roomResolution(self::ROOM_UNAVAILABLE);
        }

        $actualRoomId = (int)($roomInfo['room_id'] ?? $roomId);
        $ruid = (int)($roomInfo['uid'] ?? 0);
        $parentAreaId = (int)($roomInfo['parent_area_id'] ?? 0);
        $areaId = (int)($roomInfo['area_id'] ?? 0);
        if ($actualRoomId <= 0 || $ruid <= 0 || $parentAreaId <= 0 || $areaId <= 0) {
            return $this->roomResolution(self::ROOM_UNAVAILABLE);
        }

        return $this->roomResolution(self::ROOM_AVAILABLE, [
            'room_id' => $actualRoomId,
            'ruid' => $ruid,
            'parent_area_id' => $parentAreaId,
            'area_id' => $areaId,
            'room_title' => trim((string)($roomInfo['title'] ?? '')),
            'room_uname' => trim((string)($response['data']['anchor_info']['base_info']['uname'] ?? '')),
        ]);
    }

    private function postponeForNoRoom(
        CarnivalContext $context,
        string $reason,
        int $candidateCount,
    ): CarnivalStepResult
    {
        $backoff = $this->stateStore->watchProbeBackoff();
        $previousMisses = $backoff !== null && $backoff['date'] === $context->bizDate
            ? $backoff['misses']
            : 0;
        $misses = min(count(self::NO_ROOM_BACKOFF_SECONDS), $previousMisses + 1);
        $delay = self::NO_ROOM_BACKOFF_SECONDS[$misses - 1];
        $nextProbeAt = $context->now + $delay;

        $this->stateStore->putWatchProbeBackoff($context->bizDate, $misses, $nextProbeAt);
        $this->log('notice', "次元奇旅: {$reason}，{$delay} 秒后再次探测", [
            '候选数' => $candidateCount,
            '连续未找到' => $misses,
            'next_probe_at' => $nextProbeAt,
        ]);

        return CarnivalStepResult::postponed((float)$delay, "{$reason}，{$delay}s 后重试");
    }

    /**
     * @param array{room_id: int, ruid: int, parent_area_id: int, area_id: int, room_title: string, room_uname: string}|null $room
     * @return array{
     *   outcome: string,
     *   room: array{room_id: int, ruid: int, parent_area_id: int, area_id: int, room_title: string, room_uname: string}|null,
     *   error: string
     * }
     */
    private function roomResolution(string $outcome, ?array $room = null, string $error = ''): array
    {
        return [
            'outcome' => $outcome,
            'room' => $room,
            'error' => $error,
        ];
    }

    private function clearWatchState(): void
    {
        $this->stateStore->clearWatchSession();
        $this->stateStore->clearWatchProbeBackoff();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
