<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Automation\Follow\TemporaryFollowStore;
use Bhp\Automation\Follow\UnfollowQueueStore;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Follow\FollowStateResolver;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\TaskStatus;

/**
 * 取关清理（R5 后半）。
 *
 * 安全约束（不可退让）：只取关本插件自己关注过的 UP 主。
 * 出队后会再查一次 TemporaryFollowStore：若记录 precheck_following=true
 * （即关注动作之前用户就已关注），直接出队且不发取关请求。这是第二道保险，
 * 第一道在 FollowTaskRunner —— 原有关注根本不会入队。
 *
 * 取关时机：
 *   - 跨天的遗留项 → 立即取关（保证临时关注不会长期滞留）
 *   - 当天的项     → 等关注类任务不再处于进行中再取关
 *     （任务配置 backwardsCounters=false，取关不会回退进度；此处仍保守等待以免影响计数）
 */
final class CleanupFollowTaskRunner implements TaskRunnerInterface
{
    private const MAX_UNFOLLOWS_PER_TICK = 3;
    private const MAX_ATTEMPTS = 5;
    private const RETRY_DELAY_SECONDS = 900;

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
        return 'cleanup_follow';
    }

    public function run(CarnivalContext $context): CarnivalStepResult
    {
        if ($this->accountKey === '') {
            return CarnivalStepResult::skipped('取关清理: 账号标识为空，跳过');
        }
        if (!$this->unfollowQueueStore->hasPending($this->accountKey)) {
            return CarnivalStepResult::skipped('取关清理: 队列为空');
        }

        $followTasksPending = $this->hasPendingFollowTask($context);
        $processed = 0;
        $held = 0;
        $protected = 0;

        while ($processed < self::MAX_UNFOLLOWS_PER_TICK) {
            $item = $this->unfollowQueueStore->claimNext($this->accountKey, $context->now);
            if (!is_array($item)) {
                break;
            }

            $mid = (int)($item['uid'] ?? 0);
            $bizDate = trim((string)($item['source_biz_date'] ?? ''));
            $activityId = trim((string)($item['activity_id'] ?? ''));
            $taskId = trim((string)($item['task_id'] ?? ''));
            $attempts = max(0, (int)($item['attempts'] ?? 0));

            if ($mid <= 0 || $bizDate === '') {
                $this->unfollowQueueStore->markDone($this->accountKey, (string)$mid, $bizDate, $context->now);
                continue;
            }

            // 第二道保险：原有关注绝不取关
            if ($this->isPreexistingFollow($mid, $bizDate, $activityId, $taskId)) {
                $this->unfollowQueueStore->markDone($this->accountKey, (string)$mid, $bizDate, $context->now);
                $protected++;
                $this->log('warning', '次元奇旅: 队列中存在用户原有关注，已跳过取关并出队', ['mid' => $mid]);
                continue;
            }

            // 当天的项在关注任务仍进行中时暂缓，避免影响计数
            // 注意：暂缓不做重试上限判定 —— claimNext 每次取出都会自增 attempts，
            // 若让暂缓消耗重试次数，临时关注会在若干轮后被静默丢弃且从未取关。
            // 滞留上界由「跨天必取关」保证。
            if ($bizDate === $context->bizDate && $followTasksPending) {
                $this->unfollowQueueStore->markRetry(
                    $this->accountKey,
                    (string)$mid,
                    $bizDate,
                    $context->now + self::RETRY_DELAY_SECONDS,
                    '关注任务进行中，暂缓取关',
                    $context->now,
                );
                $held++;
                continue;
            }

            $response = $this->apiRelation->modify($mid);
            $this->authFailureClassifier->assertNotAuthFailure($response, '次元奇旅取关时账号未登录');

            $code = (int)($response['code'] ?? -1);
            $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));

            if ($code === 0) {
                $this->unfollowQueueStore->markDone($this->accountKey, (string)$mid, $bizDate, $context->now);
                if ($activityId !== '' && $taskId !== '') {
                    $this->temporaryFollowStore->markDone(
                        $this->accountKey,
                        (string)$mid,
                        $bizDate,
                        $activityId,
                        $taskId,
                        $context->now,
                    );
                }
                $this->followStateResolver->forget($mid);
                $processed++;
                $this->log('info', '次元奇旅: 已取关临时关注', ['mid' => $mid]);
                continue;
            }

            $this->unfollowQueueStore->markRetry(
                $this->accountKey,
                (string)$mid,
                $bizDate,
                $context->now + self::RETRY_DELAY_SECONDS,
                "{$code} -> {$message}",
                $context->now,
            );
            $this->log('warning', '次元奇旅: 取关失败，已排入重试', [
                'mid' => $mid,
                'code' => $code,
                'message' => $message,
                'attempts' => $attempts,
            ]);

            // 真实取关失败才计入重试上限；超限后出队，避免无限重试卡住队列
            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->unfollowQueueStore->markDone($this->accountKey, (string)$mid, $bizDate, $context->now);
                $this->log('warning', '次元奇旅: 取关重试超上限，放弃该项（该 UP 主仍处于关注状态，可手动取关）', [
                    'mid' => $mid,
                    'attempts' => $attempts,
                ]);
            }

            return CarnivalStepResult::failed(900.0, "取关失败 {$code} -> {$message}");
        }

        if ($processed === 0) {
            $summary = sprintf('取关清理: 本轮未取关（暂缓 %d，保护 %d）', $held, $protected);

            return $held > 0
                ? CarnivalStepResult::postponed((float)self::RETRY_DELAY_SECONDS, $summary)
                : CarnivalStepResult::skipped($summary);
        }

        return CarnivalStepResult::done("取关 {$processed} 个临时关注");
    }

    /**
     * 是否仍有关注类任务在进行中
     */
    private function hasPendingFollowTask(CarnivalContext $context): bool
    {
        foreach (array_keys($context->snapshot->followTasks) as $taskId) {
            $taskId = (string)$taskId;
            $status = $context->taskStatus($taskId);
            if (in_array($status, [TaskStatus::CLAIMABLE, TaskStatus::FINISHED], true)) {
                continue;
            }

            $limit = $context->taskLimit($taskId);
            if ($limit > 0 && $context->taskCurrentValue($taskId) >= $limit) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * 该 mid 是否为用户原有关注（关注动作之前就已关注）
     */
    private function isPreexistingFollow(int $mid, string $bizDate, string $activityId, string $taskId): bool
    {
        if ($activityId === '' || $taskId === '') {
            return false;
        }

        $record = $this->temporaryFollowStore->get($this->accountKey, (string)$mid, $bizDate, $activityId, $taskId);
        if (!is_array($record)) {
            return false;
        }

        return ($record['precheck_following'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
