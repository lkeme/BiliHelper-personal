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

final class CleanupUnfollowQueueNodeRunner implements NodeRunnerInterface
{
    private const STEP_DELAY_SECONDS = 15;
    private const RETRY_DELAY_SECONDS = 300;

    /**
     * @var callable(int): array<string, mixed>
     */
    private readonly mixed $unfollowAction;
    private readonly AuthFailureClassifier $authFailureClassifier;
    private readonly ?ApiRelation $apiRelation;
    private readonly ?TemporaryFollowStore $temporaryFollowStore;

    public function __construct(
        private readonly UnfollowQueueStore $queueStore,
        private readonly string $accountKey,
        ?callable $unfollowAction = null,
        ?AuthFailureClassifier $authFailureClassifier = null,
        ?ApiRelation $apiRelation = null,
        ?TemporaryFollowStore $temporaryFollowStore = null,
    ) {
        $this->apiRelation = $apiRelation;
        $this->temporaryFollowStore = $temporaryFollowStore;
        $this->unfollowAction = $unfollowAction ?? function (int $uid): array {
            if (!$this->apiRelation instanceof ApiRelation) {
                throw new \LogicException('CleanupUnfollowQueueNodeRunner requires an ApiRelation dependency.');
            }

            return $this->apiRelation->modify($uid);
        };
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    public function type(): string
    {
        return 'cleanup_unfollow_queue';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $item = $this->queueStore->claimNext($this->accountKey, $now);
        if (!is_array($item)) {
            try {
                $recovered = $this->recoverTemporaryFollow($now);
                if ($recovered instanceof ActivityNodeResult) {
                    return $recovered;
                }
            } catch (RequestException $exception) {
                return $this->buildWaitingResult(
                    $now,
                    '临时关注恢复请求失败，稍后重试: ' . $exception->getMessage(),
                );
            } catch (\Throwable $throwable) {
                return $this->buildWaitingResult(
                    $now,
                    '临时关注恢复失败，稍后重试: ' . $throwable->getMessage(),
                );
            }

            return $this->buildIdleResult($now);
        }

        $uid = trim((string)($item['uid'] ?? ''));
        $sourceBizDate = trim((string)($item['source_biz_date'] ?? ''));
        if ($uid === '' || $sourceBizDate === '') {
            return new ActivityNodeResult(true, '待取关队列项缺少必要字段，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        try {
            $response = (array)($this->unfollowAction)((int)$uid);
        } catch (RequestException $exception) {
            $this->queueStore->markRetry(
                $this->accountKey,
                $uid,
                $sourceBizDate,
                $now + self::RETRY_DELAY_SECONDS,
                $exception->getMessage(),
                $now,
            );

            return $this->buildWaitingResult(
                $now,
                sprintf('待取关 UID=%s 请求失败，稍后重试', $uid),
            );
        }

        $this->authFailureClassifier->assertNotAuthFailure($response, '回收临时关注时账号未登录');
        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            $this->queueStore->markRetry(
                $this->accountKey,
                $uid,
                $sourceBizDate,
                $now + self::RETRY_DELAY_SECONDS,
                'API 返回 -500',
                $now,
            );

            return $this->buildWaitingResult(
                $now,
                sprintf('待取关 UID=%s 返回 -500，稍后重试', $uid),
            );
        }

        if (!$this->isSuccessfulResponse($response)) {
            $message = trim((string)($response['message'] ?? $response['msg'] ?? '取消关注失败'));
            $this->queueStore->markRetry(
                $this->accountKey,
                $uid,
                $sourceBizDate,
                $now + self::RETRY_DELAY_SECONDS,
                $message,
                $now,
            );

            return $this->buildWaitingResult(
                $now,
                sprintf('待取关 UID=%s 执行失败，稍后重试', $uid),
            );
        }

        $this->queueStore->markDone($this->accountKey, $uid, $sourceBizDate, $now);
        $this->markTemporaryFollowDone($item, $uid, $sourceBizDate, $now);
        if (!$this->queueStore->hasPending($this->accountKey)) {
            return new ActivityNodeResult(true, sprintf('待取关 UID=%s 已完成，队列已清空', $uid), [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
                'context_patch' => [
                    'cleanup_last_uid' => $uid,
                    'cleanup_last_source_biz_date' => $sourceBizDate,
                    'cleanup_last_error' => '',
                ],
            ], $now);
        }

        return new ActivityNodeResult(true, sprintf('待取关 UID=%s 已完成，继续处理队列', $uid), [
            'node_status' => ActivityNodeStatus::WAITING,
            'next_run_at' => $this->resolveNextRunAt($now),
            'context_patch' => [
                'cleanup_last_uid' => $uid,
                'cleanup_last_source_biz_date' => $sourceBizDate,
                'cleanup_last_error' => '',
            ],
        ], $now);
    }

    private function buildIdleResult(int $now): ActivityNodeResult
    {
        $nextRunAt = $this->queueStore->nextPendingRunAt($this->accountKey);
        if ($nextRunAt === null) {
            return new ActivityNodeResult(true, '待取关队列已清空', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
            ], $now);
        }

        return new ActivityNodeResult(true, '待取关队列暂无到期项，等待继续', [
            'node_status' => ActivityNodeStatus::WAITING,
            'next_run_at' => max($now + 1, $nextRunAt),
        ], $now);
    }

    private function buildWaitingResult(int $now, string $message): ActivityNodeResult
    {
        return new ActivityNodeResult(false, $message, [
            'node_status' => ActivityNodeStatus::WAITING,
            'next_run_at' => $this->resolveNextRunAt($now),
        ], $now);
    }

    private function resolveNextRunAt(int $now): int
    {
        $nextPendingRunAt = $this->queueStore->nextPendingRunAt($this->accountKey);
        if ($nextPendingRunAt === null) {
            return $now + self::STEP_DELAY_SECONDS;
        }

        return max($now + self::STEP_DELAY_SECONDS, $nextPendingRunAt);
    }

    private function recoverTemporaryFollow(int $now): ?ActivityNodeResult
    {
        if (!$this->temporaryFollowStore instanceof TemporaryFollowStore || trim($this->accountKey) === '') {
            return null;
        }

        $item = $this->temporaryFollowStore->claimNextRecoverable(trim($this->accountKey), $now);
        if (!is_array($item)) {
            return null;
        }

        $uid = trim((string)($item['uid'] ?? ''));
        $sourceBizDate = trim((string)($item['source_biz_date'] ?? ''));
        $activityId = trim((string)($item['activity_id'] ?? ''));
        $taskId = trim((string)($item['task_id'] ?? ''));
        $state = trim((string)($item['state'] ?? ''));
        if ($uid === '' || $sourceBizDate === '' || $taskId === '') {
            return new ActivityNodeResult(false, '临时关注恢复记录缺少必要字段，稍后重试', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }

        [$isFollowing, $relationError] = $this->probeFollowingState((int)$uid);
        if ($relationError !== null) {
            return new ActivityNodeResult(false, sprintf('临时关注 UID=%s 恢复时关系校验失败，稍后重试: %s', $uid, $relationError), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }

        if (!$isFollowing) {
            $this->temporaryFollowStore->markCancelled(
                trim($this->accountKey),
                $uid,
                $sourceBizDate,
                $activityId,
                $taskId,
                $now,
                '恢复时检测为未关注，视为无需回收',
            );

            return new ActivityNodeResult(true, sprintf('临时关注 UID=%s 恢复时已是未关注状态，标记完成', $uid), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + 1,
                'context_patch' => [
                    'cleanup_last_uid' => $uid,
                    'cleanup_last_source_biz_date' => $sourceBizDate,
                    'cleanup_last_error' => '',
                ],
            ], $now);
        }

        $this->queueStore->enqueue(trim($this->accountKey), $uid, $sourceBizDate, [
            'activity_id' => $activityId,
            'task_id' => $taskId,
        ]);
        $this->temporaryFollowStore->markCleanupEnqueued(
            trim($this->accountKey),
            $uid,
            $sourceBizDate,
            $activityId,
            $taskId,
            $now,
        );

        $message = $state === TemporaryFollowStore::STATE_CLEANUP_ENQUEUED
            ? sprintf('临时关注 UID=%s 已存在待回收状态，继续处理队列', $uid)
            : sprintf('临时关注 UID=%s 已恢复并补入待取关队列', $uid);

        return new ActivityNodeResult(true, $message, [
            'node_status' => ActivityNodeStatus::WAITING,
            'next_run_at' => $now + 1,
            'context_patch' => [
                'cleanup_last_uid' => $uid,
                'cleanup_last_source_biz_date' => $sourceBizDate,
                'cleanup_last_error' => '',
            ],
        ], $now);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isSuccessfulResponse(array $response): bool
    {
        $code = (int)($response['code'] ?? -1);
        $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));

        return $code === 0 || str_contains($message, '未关注') || str_contains($message, '重复');
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

        // attribute 是枚举值：0=未关注，1=悄悄关注（历史值），2=已关注，6=已互粉，128=已拉黑
        $attr = (int)$attribute;
        return [ApiRelation::isFollowingAttribute($attr), null];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function markTemporaryFollowDone(array $item, string $uid, string $sourceBizDate, int $now): void
    {
        if (!$this->temporaryFollowStore instanceof TemporaryFollowStore || trim($this->accountKey) === '') {
            return;
        }

        $activityId = trim((string)($item['activity_id'] ?? ''));
        $taskId = trim((string)($item['task_id'] ?? ''));
        if ($taskId === '') {
            return;
        }

        $this->temporaryFollowStore->markDone(
            trim($this->accountKey),
            $uid,
            $sourceBizDate,
            $activityId,
            $taskId,
            $now,
        );
    }
}
