<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Automation\Follow\TemporaryFollowStore;
use Bhp\Automation\Follow\UnfollowQueueStore;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Follow\FollowStateResolver;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\CarnivalIds;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\TaskStatus;

/**
 * 关注类任务（R5）。
 *
 * 安全约束（不可退让）：
 *   1. 已关注的 UP 主不重复关注
 *   2. 用户原有的关注绝不取关 —— 判定为已关注时写入 precheck_following=true 且不入取关队列
 *   3. 关注状态无法判定时一律跳过（fail-safe），宁可少做进度也不误取关
 *
 * 每 tick 最多处理 MAX_FOLLOWS_PER_TICK 个目标，避免密集写请求触发风控。
 */
final class FollowTaskRunner implements TaskRunnerInterface
{
    private const MAX_FOLLOWS_PER_TICK = 2;

    /**
     * 单 tick 最多做多少次关注状态权威查询。
     *
     * 目标池有 50 个，若账号已关注其中大部分，不设上限时首轮会把 50 个目标全查一遍
     * 才凑够 2 个可关注目标 —— 请求突发且拖长 tick。已判定的目标会记账，下轮直接跳过，
     * 所以分多轮消化不会丢进度。
     */
    private const MAX_STATE_CHECKS_PER_TICK = 6;

    private readonly ApiRelation $apiRelation;
    private readonly FollowStateResolver $followStateResolver;
    private readonly TemporaryFollowStore $temporaryFollowStore;
    private readonly UnfollowQueueStore $unfollowQueueStore;
    private readonly AuthFailureClassifier $authFailureClassifier;
    private readonly string $accountKey;

    /** @var \Closure(string, string, array<string, mixed>): void */
    private readonly \Closure $logger;

    /**
     * @param callable(string, string, array<string, mixed>): void $logger
     */
    public function __construct(
        ApiRelation $apiRelation,
        FollowStateResolver $followStateResolver,
        TemporaryFollowStore $temporaryFollowStore,
        UnfollowQueueStore $unfollowQueueStore,
        string $accountKey,
        callable $logger,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiRelation = $apiRelation;
        $this->followStateResolver = $followStateResolver;
        $this->temporaryFollowStore = $temporaryFollowStore;
        $this->unfollowQueueStore = $unfollowQueueStore;
        $this->accountKey = trim($accountKey);
        $this->logger = \Closure::fromCallable($logger);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    public function key(): string
    {
        return 'follow';
    }

    public function run(CarnivalContext $context): CarnivalStepResult
    {
        if ($this->accountKey === '') {
            return CarnivalStepResult::skipped('关注: 账号标识为空，跳过');
        }

        $snapshot = $context->snapshot;
        if ($snapshot->followTasks === []) {
            return CarnivalStepResult::skipped('关注: 无关注任务配置');
        }
        if ($snapshot->followTargets === []) {
            return CarnivalStepResult::skipped('关注: 未解析到关注目标');
        }

        $pendingTaskIds = $this->pendingTaskIds($context);
        if ($pendingTaskIds === []) {
            return CarnivalStepResult::skipped('关注: 关注类任务均已达成');
        }

        // 记账与停止判据都必须绑定「当前目标池实际计入的那个任务」。
        // 实测 anchorConfigList 池只推进 ANCHOR_POOL_TASK_ID；若该任务已达成，
        // 继续用同一池关注不会让任何 counter 变动，属于纯无效写请求，必须停。
        $taskId = CarnivalIds::ANCHOR_POOL_TASK_ID;
        if (!in_array($taskId, $pendingTaskIds, true)) {
            return CarnivalStepResult::skipped(
                '关注: 当前目标池对应的任务已达成；其余关注任务需要稿件 UP 主池（本期未实现），跳过'
            );
        }

        // counter 变动观察：逐轮记录三个任务进度，便于回溯归属
        $this->logCounterSnapshot($context);

        $activityId = $snapshot->activityId;
        $processed = 0;
        $stateChecks = 0;
        $skippedAlreadyFollowing = 0;
        $skippedUndecidable = 0;

        foreach ($snapshot->followTargets as $mid) {
            if ($processed >= self::MAX_FOLLOWS_PER_TICK) {
                break;
            }
            if ($stateChecks >= self::MAX_STATE_CHECKS_PER_TICK) {
                break;
            }
            if ($mid <= 0) {
                continue;
            }

            if ($this->alreadyHandled($mid, $context->bizDate, $activityId, $taskId)) {
                continue;
            }

            $stateChecks++;
            $following = $this->followStateResolver->queryFollowing($mid);

            if ($following === true) {
                // 用户原有关注：登记为 precheck_following=true / CANCELLED，绝不入取关队列
                $this->temporaryFollowStore->markAlreadyFollowed(
                    $this->accountKey,
                    (string)$mid,
                    $context->bizDate,
                    $activityId,
                    $taskId,
                    $context->now,
                );
                $skippedAlreadyFollowing++;
                continue;
            }

            if ($following === null) {
                // 无法判定 —— 不关注，也不记账，留待下轮重试
                $skippedUndecidable++;
                $this->log('debug', '次元奇旅: 关注状态无法判定，本轮跳过该目标', ['mid' => $mid]);
                continue;
            }

            $this->temporaryFollowStore->ensurePlanned(
                $this->accountKey,
                (string)$mid,
                $context->bizDate,
                $activityId,
                $taskId,
                $context->now,
            );

            $response = $this->apiRelation->follow($mid);
            $this->authFailureClassifier->assertNotAuthFailure($response, '次元奇旅关注时账号未登录');

            $code = (int)($response['code'] ?? -1);
            $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));

            if ($code !== 0) {
                $this->temporaryFollowStore->markCancelled(
                    $this->accountKey,
                    (string)$mid,
                    $context->bizDate,
                    $activityId,
                    $taskId,
                    $context->now,
                    "{$code} -> {$message}",
                );
                $this->log('warning', '次元奇旅: 关注失败，本轮停止关注', [
                    'mid' => $mid,
                    'code' => $code,
                    'message' => $message,
                ]);

                return CarnivalStepResult::failed(1800.0, "关注失败 {$code} -> {$message}");
            }

            $this->followStateResolver->markFollowed($mid);
            $this->temporaryFollowStore->markCleanupPending(
                $this->accountKey,
                (string)$mid,
                $context->bizDate,
                $activityId,
                $taskId,
                $context->now,
            );
            $this->unfollowQueueStore->enqueue($this->accountKey, (string)$mid, $context->bizDate, [
                'activity_id' => $activityId,
                'task_id' => $taskId,
            ]);
            $this->temporaryFollowStore->markCleanupEnqueued(
                $this->accountKey,
                (string)$mid,
                $context->bizDate,
                $activityId,
                $taskId,
                $context->now,
            );

            $processed++;
            $this->log('info', '次元奇旅: 已关注并加入待取关队列', ['mid' => $mid]);
        }

        if ($processed === 0) {
            return CarnivalStepResult::skipped(sprintf(
                '关注: 本轮无新增（查询 %d 次，原有关注跳过 %d，状态未定 %d）',
                $stateChecks,
                $skippedAlreadyFollowing,
                $skippedUndecidable,
            ));
        }

        return CarnivalStepResult::done(sprintf('关注 %d 个目标（查询 %d 次）', $processed, $stateChecks));
    }

    /**
     * 尚未达成的关注任务
     *
     * @return string[]
     */
    private function pendingTaskIds(CarnivalContext $context): array
    {
        $pending = [];
        foreach (array_keys($context->snapshot->followTasks) as $taskId) {
            $taskId = (string)$taskId;
            $status = $context->taskStatus($taskId);
            if (in_array($status, [TaskStatus::CLAIMABLE, TaskStatus::FINISHED], true)) {
                continue;
            }

            // 进度已满但状态未刷新时同样不再关注
            $limit = $context->taskLimit($taskId);
            if ($limit > 0 && $context->taskCurrentValue($taskId) >= $limit) {
                continue;
            }

            $pending[] = $taskId;
        }

        return $pending;
    }

    /**
     * 该目标在本插件记账中是否已处理过
     */
    private function alreadyHandled(int $mid, string $bizDate, string $activityId, string $taskId): bool
    {
        $record = $this->temporaryFollowStore->get($this->accountKey, (string)$mid, $bizDate, $activityId, $taskId);
        if (!is_array($record)) {
            return false;
        }

        return in_array((string)($record['state'] ?? ''), [
            TemporaryFollowStore::STATE_CLEANUP_PENDING,
            TemporaryFollowStore::STATE_CLEANUP_ENQUEUED,
            TemporaryFollowStore::STATE_DONE,
            TemporaryFollowStore::STATE_CANCELLED,
        ], true);
    }

    /**
     * 记录三个关注任务的当前进度，用于确认任务与目标池的对应关系
     */
    private function logCounterSnapshot(CarnivalContext $context): void
    {
        $rows = [];
        foreach ($context->snapshot->followTasks as $taskId => $task) {
            $taskId = (string)$taskId;
            $rows[] = sprintf(
                '%s(%s) %d/%d status=%d',
                (string)$task['task_name'],
                (string)$task['counter'],
                $context->taskCurrentValue($taskId),
                $context->taskLimit($taskId),
                $context->taskStatus($taskId),
            );
        }

        $this->log('info', '次元奇旅: 关注任务进度快照', ['tasks' => implode(' | ', $rows)]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
