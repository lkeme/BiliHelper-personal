<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

use Bhp\Api\Api\X\ActivityComponents\ApiMission;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\State\CarnivalStateStore;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\TaskStatus;

/**
 * 累计签到领奖（R3）。
 *
 * 4 档 checkpoint：7 天（抽奖次数*10）、14 天（*20）、30 天（*30）、45 天（大会员月卡）。
 * 单 checkpoint 活动期内仅可领一次，已领 sid 记入 claimed_sids 避免重复请求。
 *
 * 领奖接口与参数取自 award-exchange 页源码，逐字段核对过：
 *   POST /x/activity_components/mission/receive
 *   { task_id=<checkpoint sid>, activity_id, activity_name, task_name, reward_name, receive_from=missionPage }
 */
final class ClaimRewardTaskRunner implements TaskRunnerInterface
{
    private readonly ApiMission $apiMission;
    private readonly CarnivalStateStore $stateStore;
    private readonly AuthFailureClassifier $authFailureClassifier;

    /** @var \Closure(string, string, array<string, mixed>): void */
    private readonly \Closure $logger;

    /** @var \Closure(string, string): void */
    private readonly \Closure $notifier;

    /**
     * @param callable(string, string, array<string, mixed>): void $logger
     * @param callable(string, string): void $notifier
     */
    public function __construct(
        ApiMission $apiMission,
        CarnivalStateStore $stateStore,
        callable $logger,
        callable $notifier,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiMission = $apiMission;
        $this->stateStore = $stateStore;
        $this->logger = \Closure::fromCallable($logger);
        $this->notifier = \Closure::fromCallable($notifier);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    public function key(): string
    {
        return 'claim_reward';
    }

    public function run(CarnivalContext $context): CarnivalStepResult
    {
        $snapshot = $context->snapshot;
        $taskId = $snapshot->signInTaskId;
        if ($taskId === '' || $snapshot->signInCheckpoints === []) {
            return CarnivalStepResult::skipped('领奖: 缺少签到任务或 checkpoint 配置');
        }

        $achievedDays = $this->achievedDays($context, $taskId);
        $claimable = $this->resolveClaimable($context, $taskId, $achievedDays);
        if ($claimable === []) {
            return CarnivalStepResult::skipped('领奖: 暂无可领取的 checkpoint');
        }

        $claimedCount = 0;
        foreach ($claimable as $sid => $checkpoint) {
            $response = $this->apiMission->receive(
                $sid,
                $snapshot->activityId,
                $snapshot->activityName,
                $snapshot->signInTaskName,
                (string)$checkpoint['reward_name'],
            );
            $this->authFailureClassifier->assertNotAuthFailure($response, '次元奇旅领奖时账号未登录');

            $code = (int)($response['code'] ?? -1);
            $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));

            if ($code === 0) {
                $this->stateStore->markClaimed($sid);
                $claimedCount++;
                $this->log('notice', '次元奇旅: 领取累计签到奖励成功', [
                    '档位' => $checkpoint['days'] . ' 天',
                    '奖励' => $checkpoint['reward_name'],
                ]);
                ($this->notifier)('award', sprintf(
                    '次元奇旅: 累计签到 %d 天奖励已领取 -> %s',
                    (int)$checkpoint['days'],
                    (string)$checkpoint['reward_name'],
                ));
                continue;
            }

            // 已领过 / 无资格 —— 记入已领避免每 tick 重试
            if ($this->isAlreadyClaimed($message)) {
                $this->stateStore->markClaimed($sid);
                $this->log('info', '次元奇旅: 该档奖励已领取或无资格，标记跳过', [
                    '档位' => $checkpoint['days'] . ' 天',
                    'code' => $code,
                    'message' => $message,
                ]);
                continue;
            }

            $this->log('warning', '次元奇旅: 领奖失败', [
                '档位' => $checkpoint['days'] . ' 天',
                'code' => $code,
                'message' => $message,
            ]);

            return CarnivalStepResult::failed(1800.0, "领奖失败 {$code} -> {$message}");
        }

        return $claimedCount > 0
            ? CarnivalStepResult::done("领取 {$claimedCount} 档累计签到奖励")
            : CarnivalStepResult::skipped('领奖: 本轮无新增领取');
    }

    /**
     * 已达成的累计天数
     */
    private function achievedDays(CarnivalContext $context, string $taskId): int
    {
        $detail = $context->taskDetail($taskId);
        $accumulative = $detail['accumulative_count'] ?? null;
        if (is_numeric($accumulative) && (int)$accumulative > 0) {
            return (int)$accumulative;
        }

        return $context->taskCurrentValue($taskId);
    }

    /**
     * 筛出可领取的 checkpoint。
     *
     * 优先采信 totalv2 返回的 checkpoint status（3 = 可领/已达成），
     * 无该字段时退化为「累计天数 >= 档位阈值」。
     *
     * @return array<string, array{days: int, reward_name: string}>
     */
    private function resolveClaimable(CarnivalContext $context, string $taskId, int $achievedDays): array
    {
        $remoteStatus = $this->remoteCheckpointStatus($context, $taskId);
        $claimable = [];

        foreach ($context->snapshot->signInCheckpoints as $sid => $checkpoint) {
            if ($this->stateStore->isClaimed($sid)) {
                continue;
            }

            $status = $remoteStatus[$sid] ?? null;
            if ($status !== null) {
                // 3 表示该档已达成可领取
                if ($status === 3) {
                    $claimable[$sid] = $checkpoint;
                }
                continue;
            }

            if ((int)$checkpoint['days'] > 0 && $achievedDays >= (int)$checkpoint['days']) {
                $claimable[$sid] = $checkpoint;
            }
        }

        return $claimable;
    }

    /**
     * @return array<string, int> sid => status
     */
    private function remoteCheckpointStatus(CarnivalContext $context, string $taskId): array
    {
        $detail = $context->taskDetail($taskId);
        $source = $detail['accumulative_check_points'] ?? $detail['checkpoints'] ?? null;
        if (!is_array($source)) {
            return [];
        }

        $statuses = [];
        foreach ($source as $checkpoint) {
            if (!is_array($checkpoint)) {
                continue;
            }

            $sid = trim((string)($checkpoint['sid'] ?? $checkpoint['ztasksid'] ?? ''));
            if ($sid === '') {
                continue;
            }

            $statuses[$sid] = (int)($checkpoint['status'] ?? 0);
        }

        return $statuses;
    }

    /**
     * 判断返回是否属于「已领过 / 无资格」而非真实故障
     */
    private function isAlreadyClaimed(string $message): bool
    {
        foreach (['已领取', '已经领取', '已领完', '无资格', '不满足', '已参与'] as $needle) {
            if ($message !== '' && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
