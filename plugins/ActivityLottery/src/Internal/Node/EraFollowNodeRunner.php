<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
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
        ?UnfollowQueueStore $unfollowQueueStore = null,
        string $accountKey = '',
    ) {
        $this->apiRelation = $apiRelation;
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
        try {
            $response = (array)($this->followAction)((int)$uid);
        } catch (RequestException $exception) {
            return new ActivityNodeResult(false, '关注任务执行失败: ' . $exception->getMessage(), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }

        $this->authFailureClassifier->assertNotAuthFailure($response, '关注任务执行时账号未登录');
        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            return new ActivityNodeResult(false, '关注任务执行失败，稍后重试', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }
        if (!$this->isSuccessfulResponse($response)) {
            return new ActivityNodeResult(false, '关注任务执行失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $temporaryUids = $this->normalizedUidList($state['temporary_follow_uids'] ?? null);
        if ($this->shouldTrackTemporaryFollow($response)) {
            $temporaryUids[] = $uid;
        }

        $nextState = array_replace($state, [
            'follow_target_index' => $index + 1,
            'temporary_follow_uids' => array_values(array_unique(array_map('strval', $temporaryUids))),
        ]);
        [$nextState, $queueFlushError] = $this->flushPendingCleanupQueue($nextState, $flow->bizDate(), [
            'activity_id' => trim((string)($taskView->activity()['activity_id'] ?? '')),
            'task_id' => $taskView->taskId(),
        ]);
        if ($queueFlushError !== null) {
            return new ActivityNodeResult(false, '关注任务成功，但待取关队列写入失败，稍后重试: ' . $queueFlushError, [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        if (($index + 1) < count($uids)) {
            return new ActivityNodeResult(true, '关注任务已推进到下一目标', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::STEP_DELAY_SECONDS,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        return new ActivityNodeResult(true, '关注任务执行完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $taskView->replaceTaskRuntime($nextState),
        ], $now);
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
     * 仅把本次任务实际新关注成功的 UID 纳入回收列表，避免误取消用户原本已关注的账号。
     *
     * @param array<string, mixed> $response
     */
    private function shouldTrackTemporaryFollow(array $response): bool
    {
        $code = (int)($response['code'] ?? -1);
        $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));
        return $code === 0 && !str_contains($message, '已关注');
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

