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

    public function __construct(
        private readonly UnfollowQueueStore $queueStore,
        private readonly string $accountKey,
        ?callable $unfollowAction = null,
        ?AuthFailureClassifier $authFailureClassifier = null,
        ?ApiRelation $apiRelation = null,
    ) {
        $this->apiRelation = $apiRelation;
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

    /**
     * @param array<string, mixed> $response
     */
    private function isSuccessfulResponse(array $response): bool
    {
        $code = (int)($response['code'] ?? -1);
        $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));

        return $code === 0 || str_contains($message, '未关注') || str_contains($message, '重复');
    }
}
