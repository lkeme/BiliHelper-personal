<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Queue\TemporaryFollowStore;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Queue\UnfollowQueueStore;
use Bhp\Util\Exceptions\RequestException;

final class EraFollowNodeRunner implements NodeRunnerInterface
{
    private const STEP_DELAY_SECONDS = 15;
    private const RETRY_DELAY_SECONDS = 300;
    private const RELATION_SOURCE_ACTIVITY_PAGE = 222;

    /**
     * @var callable(int): array<string, mixed>
     */
    private readonly mixed $followAction;
    private readonly AuthFailureClassifier $authFailureClassifier;
    private readonly ?ApiRelation $apiRelation;
    private readonly ?TemporaryFollowStore $temporaryFollowStore;
    private readonly ?UnfollowQueueStore $unfollowQueueStore;
    private readonly string $accountKey;

    /**
     * 初始化 EraFollowNodeRunner
     * @param callable $followAction
     * @param AuthFailureClassifier $authFailureClassifier
     * @param ApiRelation $apiRelation
     */
    public function __construct(
        ?callable $followAction = null,
        ?AuthFailureClassifier $authFailureClassifier = null,
        ?ApiRelation $apiRelation = null,
        ?TemporaryFollowStore $temporaryFollowStore = null,
        ?UnfollowQueueStore $unfollowQueueStore = null,
        string $accountKey = '',
    ) {
        $this->apiRelation = $apiRelation;
        $this->temporaryFollowStore = $temporaryFollowStore;
        $this->unfollowQueueStore = $unfollowQueueStore;
        $this->accountKey = trim($accountKey);
        $this->followAction = $followAction ?? function (int $uid): array {
            if (!$this->apiRelation instanceof ApiRelation) {
                throw new \LogicException('EraFollowNodeRunner requires an ApiRelation dependency.');
            }

            return $this->apiRelation->follow($uid, self::RELATION_SOURCE_ACTIVITY_PAGE);
        };
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    /**
     * 获取类型标识
     * @return string
     */
    public function type(): string
    {
        return 'era_task_follow';
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
            return new ActivityNodeResult(true, '关注任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        if ($taskView->resolvedTaskStatus() !== 1) {
            return new ActivityNodeResult(true, '关注任务已完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
            ], $now);
        }

        $uids = $task->targetUids();
        if ($uids === []) {
            return new ActivityNodeResult(true, '关注任务缺少目标 UID，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $state = $taskView->taskRuntime();
        [$state, $queueFlushError] = $this->flushPendingCleanupQueue($state, $flow->bizDate());
        if ($queueFlushError !== null) {
            return new ActivityNodeResult(false, '关注任务已暂存待取关 UID，稍后重试入队: ' . $queueFlushError, [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                'context_patch' => $taskView->replaceTaskRuntime($state),
            ], $now);
        }

        $index = max(0, (int)($state['follow_target_index'] ?? 0));
        if (!isset($uids[$index])) {
            return new ActivityNodeResult(true, '关注任务已完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
            ], $now);
        }

        $uid = (string)$uids[$index];
        $activityId = trim((string)($taskView->activity()['activity_id'] ?? ''));
        $meta = [
            'activity_id' => $activityId,
            'task_id' => $taskView->taskId(),
        ];

        $operationState = $this->temporaryFollowState(
            $uid,
            $flow->bizDate(),
            $activityId,
            $taskView->taskId(),
        );
        if ($operationState === TemporaryFollowStore::STATE_PLANNED) {
            [$isFollowing, $relationError] = $this->probeFollowingState((int)$uid);
            if ($relationError !== null) {
                return $this->buildWaitingResult(
                    $taskView,
                    $state,
                    $now,
                    '关注任务关系校验失败，稍后重试: ' . $relationError,
                );
            }

            if ($isFollowing) {
                $this->markCleanupPending($uid, $flow->bizDate(), $activityId, $taskView->taskId(), $now);
                $operationState = TemporaryFollowStore::STATE_CLEANUP_PENDING;
            }
        }

        if ($operationState === null) {
            [$isFollowing, $relationError] = $this->probeFollowingState((int)$uid);
            if ($relationError !== null) {
                return $this->buildWaitingResult(
                    $taskView,
                    $state,
                    $now,
                    '关注任务关系校验失败，稍后重试: ' . $relationError,
                );
            }

            if ($isFollowing) {
                $this->markAlreadyFollowed($uid, $flow->bizDate(), $activityId, $taskView->taskId(), $now);
                $nextState = $this->advanceTaskState($state, $index);
                return $this->buildAdvanceResult(
                    $taskView,
                    $nextState,
                    $uids,
                    $index,
                    $now,
                    sprintf('关注任务 UID=%s 已是关注状态，跳过', $uid),
                );
            }

            $this->ensureTemporaryFollowPlanned($uid, $flow->bizDate(), $activityId, $taskView->taskId(), $now);
            $operationState = TemporaryFollowStore::STATE_PLANNED;
        }

        if ($operationState !== TemporaryFollowStore::STATE_PLANNED) {
            if ($operationState === TemporaryFollowStore::STATE_CLEANUP_PENDING) {
                $queueError = $this->enqueueTrackedFollow($uid, $flow->bizDate(), $meta, $now);
                if ($queueError !== null) {
                    return $this->buildWaitingResult(
                        $taskView,
                        $state,
                        $now,
                        sprintf('关注任务 UID=%s 已恢复为待回收状态，但写入队列失败，稍后重试: %s', $uid, $queueError),
                    );
                }
                $this->markCleanupEnqueued($uid, $flow->bizDate(), $activityId, $taskView->taskId(), $now);
            }

            $nextState = $this->advanceTaskState($state, $index);
            $message = match ($operationState) {
                TemporaryFollowStore::STATE_CANCELLED => sprintf('关注任务 UID=%s 已确认原本就关注，跳过', $uid),
                TemporaryFollowStore::STATE_DONE => sprintf('关注任务 UID=%s 的临时关注已完成回收', $uid),
                TemporaryFollowStore::STATE_CLEANUP_ENQUEUED => sprintf('关注任务 UID=%s 已在待回收队列中，继续推进', $uid),
                default => sprintf('关注任务 UID=%s 已恢复临时关注状态并推进', $uid),
            };

            return $this->buildAdvanceResult($taskView, $nextState, $uids, $index, $now, $message);
        }

        try {
            $response = (array)($this->followAction)((int)$uid);
        } catch (RequestException $exception) {
            return $this->buildWaitingResult(
                $taskView,
                $state,
                $now,
                '关注任务执行失败: ' . $exception->getMessage(),
            );
        }

        $this->authFailureClassifier->assertNotAuthFailure($response, '关注任务执行时账号未登录');
        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            return $this->buildWaitingResult($taskView, $state, $now, '关注任务执行失败，稍后重试');
        }
        if (!$this->isSuccessfulResponse($response)) {
            [$isFollowing, $relationError] = $this->probeFollowingState((int)$uid);
            if ($relationError === null && $isFollowing) {
                $this->markCleanupPending($uid, $flow->bizDate(), $activityId, $taskView->taskId(), $now);
                $queueError = $this->enqueueTrackedFollow($uid, $flow->bizDate(), $meta, $now);
                if ($queueError !== null) {
                    return $this->buildWaitingResult(
                        $taskView,
                        $state,
                        $now,
                        sprintf('关注任务 UID=%s 已恢复为待回收状态，但写入队列失败，稍后重试: %s', $uid, $queueError),
                    );
                }
                $this->markCleanupEnqueued($uid, $flow->bizDate(), $activityId, $taskView->taskId(), $now);

                $nextState = $this->advanceTaskState($state, $index);
                return $this->buildAdvanceResult(
                    $taskView,
                    $nextState,
                    $uids,
                    $index,
                    $now,
                    sprintf('关注任务 UID=%s 响应异常，但已通过关系状态确认并入队回收', $uid),
                );
            }

            $message = trim((string)($response['message'] ?? $response['msg'] ?? '关注任务执行失败'));
            return $this->buildWaitingResult($taskView, $state, $now, $message);
        }

        [$isFollowing, $relationError] = $this->probeFollowingState((int)$uid);
        if ($relationError !== null) {
            return $this->buildWaitingResult(
                $taskView,
                $state,
                $now,
                '关注任务已提交，但关系状态确认失败，稍后重试: ' . $relationError,
            );
        }
        if (!$isFollowing) {
            return $this->buildWaitingResult(
                $taskView,
                $state,
                $now,
                sprintf('关注任务 UID=%s 已提交，但关系状态尚未生效，稍后重试确认', $uid),
            );
        }

        $this->markCleanupPending($uid, $flow->bizDate(), $activityId, $taskView->taskId(), $now);
        $queueError = $this->enqueueTrackedFollow($uid, $flow->bizDate(), $meta, $now);
        if ($queueError !== null) {
            return $this->buildWaitingResult(
                $taskView,
                $state,
                $now,
                '关注任务成功，但待取关队列写入失败，稍后重试: ' . $queueError,
            );
        }
        $this->markCleanupEnqueued($uid, $flow->bizDate(), $activityId, $taskView->taskId(), $now);

        $nextState = $this->advanceTaskState($state, $index);
        return $this->buildAdvanceResult(
            $taskView,
            $nextState,
            $uids,
            $index,
            $now,
            sprintf('关注任务 UID=%s 已确认关注并写入待回收队列', $uid),
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isSuccessfulResponse(array $response): bool
    {
        $code = (int)($response['code'] ?? -1);
        $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));
        return $code === 0 || $code === 22014 || str_contains($message, '已关注');
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function probeFollowingState(int $uid): array
    {
        if (!$this->apiRelation instanceof ApiRelation) {
            return [false, '缺少关系查询依赖'];
        }

        $response = $this->apiRelation->relationWithSelf($uid);
        $this->authFailureClassifier->assertNotAuthFailure($response, '查询关注关系时账号未登录');

        $code = (int)($response['code'] ?? -1);
        if ($code !== 0) {
            $message = trim((string)($response['message'] ?? $response['msg'] ?? '关系查询失败'));
            return [false, $message !== '' ? $message : '关系查询失败'];
        }

        $attribute = $response['data']['be_relation']['attribute'] ?? null;
        if (!is_numeric($attribute)) {
            return [false, '关系查询响应缺少 be_relation.attribute'];
        }

        return [in_array((int)$attribute, [2, 6], true), null];
    }

    private function temporaryFollowState(
        string $uid,
        string $bizDate,
        string $activityId,
        string $taskId,
    ): ?string {
        if (!$this->temporaryFollowStore instanceof TemporaryFollowStore || $this->accountKey === '') {
            return null;
        }

        $record = $this->temporaryFollowStore->get(
            $this->accountKey,
            $uid,
            $bizDate,
            $activityId,
            $taskId,
        );

        return is_array($record) ? trim((string)($record['state'] ?? '')) : null;
    }

    private function ensureTemporaryFollowPlanned(
        string $uid,
        string $bizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        if (!$this->temporaryFollowStore instanceof TemporaryFollowStore || $this->accountKey === '') {
            return;
        }

        $this->temporaryFollowStore->ensurePlanned($this->accountKey, $uid, $bizDate, $activityId, $taskId, $now);
    }

    private function markAlreadyFollowed(
        string $uid,
        string $bizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        if (!$this->temporaryFollowStore instanceof TemporaryFollowStore || $this->accountKey === '') {
            return;
        }

        $this->temporaryFollowStore->markAlreadyFollowed($this->accountKey, $uid, $bizDate, $activityId, $taskId, $now);
    }

    private function markCleanupPending(
        string $uid,
        string $bizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        if (!$this->temporaryFollowStore instanceof TemporaryFollowStore || $this->accountKey === '') {
            return;
        }

        $this->temporaryFollowStore->markCleanupPending($this->accountKey, $uid, $bizDate, $activityId, $taskId, $now);
    }

    private function markCleanupEnqueued(
        string $uid,
        string $bizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        if (!$this->temporaryFollowStore instanceof TemporaryFollowStore || $this->accountKey === '') {
            return;
        }

        $this->temporaryFollowStore->markCleanupEnqueued($this->accountKey, $uid, $bizDate, $activityId, $taskId, $now);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function enqueueTrackedFollow(string $uid, string $bizDate, array $meta, int $now): ?string
    {
        if (!$this->unfollowQueueStore instanceof UnfollowQueueStore || $this->accountKey === '') {
            return '缺少待取关队列依赖';
        }

        try {
            $this->unfollowQueueStore->enqueue($this->accountKey, $uid, $bizDate, $meta);
            return null;
        } catch (\Throwable $throwable) {
            return $throwable->getMessage();
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function advanceTaskState(array $state, int $index): array
    {
        return array_replace($state, [
            'follow_target_index' => $index + 1,
        ]);
    }

    /**
     * @param array<string, mixed> $state
     * @param string[] $uids
     */
    private function buildAdvanceResult(
        ResolvedEraTaskView $taskView,
        array $state,
        array $uids,
        int $index,
        int $now,
        string $message,
    ): ActivityNodeResult {
        if (($index + 1) < count($uids)) {
            return new ActivityNodeResult(true, $message, [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::STEP_DELAY_SECONDS,
                'context_patch' => $taskView->replaceTaskRuntime($state),
            ], $now);
        }

        return new ActivityNodeResult(true, $message, [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $taskView->replaceTaskRuntime($state),
        ], $now);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function buildWaitingResult(
        ResolvedEraTaskView $taskView,
        array $state,
        int $now,
        string $message,
    ): ActivityNodeResult {
        return new ActivityNodeResult(false, $message, [
            'node_status' => ActivityNodeStatus::WAITING,
            'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            'context_patch' => $taskView->replaceTaskRuntime($state),
        ], $now);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $meta
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function flushPendingCleanupQueue(array $state, string $bizDate, array $meta = []): array
    {
        if (!$this->unfollowQueueStore instanceof UnfollowQueueStore || $this->accountKey === '') {
            return [$state, null];
        }

        $temporaryUids = $this->normalizedUidList($state['temporary_follow_uids'] ?? null);
        if ($temporaryUids === []) {
            $state['cleanup_enqueued_uids'] = [];
            return [$state, null];
        }

        $enqueuedUids = array_values(array_intersect(
            $this->normalizedUidList($state['cleanup_enqueued_uids'] ?? null),
            $temporaryUids,
        ));

        foreach ($temporaryUids as $uid) {
            if (in_array($uid, $enqueuedUids, true)) {
                continue;
            }

            try {
                $this->unfollowQueueStore->enqueue($this->accountKey, $uid, $bizDate, $meta);
                $enqueuedUids[] = $uid;
            } catch (\Throwable $throwable) {
                $state['cleanup_enqueued_uids'] = array_values(array_unique($enqueuedUids));
                return [$state, $throwable->getMessage()];
            }
        }

        $state['cleanup_enqueued_uids'] = array_values(array_unique($enqueuedUids));
        return [$state, null];
    }

    /**
     * @return string[]
     */
    private function normalizedUidList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $uid): string => trim((string)$uid),
            $value,
        ), static fn (string $uid): bool => $uid !== ''));
    }
}

