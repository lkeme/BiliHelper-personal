<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

use Bhp\Api\Api\X\ActivityComponents\ApiMission;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Gateway\TaskProgressGateway;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\State\CarnivalStateStore;
use Bhp\Util\Exceptions\RequestException;

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
    private const CHECKPOINT_CLAIMABLE = 2;
    private const CHECKPOINT_CLAIMED = 3;

    private readonly ApiMission $apiMission;
    private readonly TaskProgressGateway $taskProgressGateway;
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
        TaskProgressGateway $taskProgressGateway,
        CarnivalStateStore $stateStore,
        callable $logger,
        callable $notifier,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiMission = $apiMission;
        $this->taskProgressGateway = $taskProgressGateway;
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

            try {
                $recheckedStatus = $this->recheckCheckpointStatus($taskId, $sid);
            } catch (RequestException $exception) {
                $this->log('debug', '次元奇旅: 领奖失败后复查任务进度异常，保留原始失败', [
                    '档位' => $checkpoint['days'] . ' 天',
                    'code' => $code,
                    'message' => $message,
                    'recheck_error' => $exception->getMessage(),
                ]);

                return $this->claimFailed($checkpoint, $code, $message);
            }

            if ($recheckedStatus === self::CHECKPOINT_CLAIMED) {
                $this->stateStore->markClaimed($sid);
                $this->log('info', '次元奇旅: 领奖接口返回异常但复查已领取，标记跳过', [
                    '档位' => $checkpoint['days'] . ' 天',
                    'code' => $code,
                    'message' => $message,
                ]);
                continue;
            }

            // 仅在复查没有当前 sid 的权威状态时，用明确的“已领取”文案末级兜底。
            if ($recheckedStatus === null && $this->isExplicitlyAlreadyClaimed($message)) {
                $this->stateStore->markClaimed($sid);
                $this->log('info', '次元奇旅: 复查无有效状态但接口明确表示已领取，标记跳过', [
                    '档位' => $checkpoint['days'] . ' 天',
                    'code' => $code,
                    'message' => $message,
                ]);
                continue;
            }

            return $this->claimFailed($checkpoint, $code, $message);
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
     * 远端 checkpoint 来源存在时仅 status=2 可领取，status=3 为已领取终态。
     * 只有远端完全未提供 checkpoint 来源字段时，才退化为累计天数判定。
     *
     * @return array<string, array{days: int, reward_name: string}>
     */
    private function resolveClaimable(CarnivalContext $context, string $taskId, int $achievedDays): array
    {
        $remoteStatuses = $this->checkpointStatuses($context->taskDetail($taskId));
        $claimable = [];

        foreach ($context->snapshot->signInCheckpoints as $checkpointSid => $checkpoint) {
            $sid = trim((string)$checkpointSid);
            if ($sid === '') {
                continue;
            }

            if ($this->stateStore->isClaimed($sid)) {
                continue;
            }

            if ($remoteStatuses !== null) {
                if (!array_key_exists($sid, $remoteStatuses)) {
                    continue;
                }

                $status = $remoteStatuses[$sid];
                if ($status === self::CHECKPOINT_CLAIMED) {
                    $this->stateStore->markClaimed($sid);
                    continue;
                }
                if ($status === self::CHECKPOINT_CLAIMABLE) {
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
     * 解析 checkpoint 状态，同时保留“来源是否存在”的语义。
     *
     * @param array<string, mixed> $detail
     * @return array<string, int>|null null=两个来源字段均不存在；array=来源存在
     */
    private function checkpointStatuses(array $detail): ?array
    {
        $hasAccumulativeSource = array_key_exists('accumulative_check_points', $detail);
        $hasCompatibleSource = array_key_exists('checkpoints', $detail);
        if (!$hasAccumulativeSource && !$hasCompatibleSource) {
            return null;
        }

        $source = null;
        if (is_array($detail['accumulative_check_points'] ?? null)) {
            $source = $detail['accumulative_check_points'];
        } elseif (is_array($detail['checkpoints'] ?? null)) {
            $source = $detail['checkpoints'];
        }
        if (!is_array($source)) {
            return [];
        }

        $statuses = [];
        foreach ($source as $checkpoint) {
            if (!is_array($checkpoint)) {
                continue;
            }

            $sid = trim((string)($checkpoint['sid'] ?? $checkpoint['ztasksid'] ?? ''));
            $status = $checkpoint['status'] ?? null;
            if (
                $sid === ''
                || !is_numeric($status)
                || (float)$status !== (float)(int)$status
            ) {
                continue;
            }

            $statuses[$sid] = (int)$status;
        }

        return $statuses;
    }

    /**
     * 写失败后复查当前 checkpoint 的权威状态。
     */
    private function recheckCheckpointStatus(string $taskId, string $sid): ?int
    {
        $snapshots = $this->taskProgressGateway->fetch([$taskId]);
        $detail = $snapshots[$taskId] ?? null;
        if (!is_array($detail)) {
            return null;
        }

        $statuses = $this->checkpointStatuses($detail);
        if ($statuses === null || !array_key_exists($sid, $statuses)) {
            return null;
        }

        return $statuses[$sid];
    }

    /**
     * 仅识别明确表示奖励已领取的文案。
     */
    private function isExplicitlyAlreadyClaimed(string $message): bool
    {
        foreach (['已领取', '已经领取', '已领完'] as $needle) {
            if ($message !== '' && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{days: int, reward_name: string} $checkpoint
     */
    private function claimFailed(array $checkpoint, int $code, string $message): CarnivalStepResult
    {
        $days = (int)$checkpoint['days'];
        $displayMessage = $message !== '' ? $message : '(empty)';
        $this->log(
            'warning',
            "次元奇旅: {$days} 天档领奖失败 code={$code} message={$displayMessage}",
            [
                '档位' => $days . ' 天',
                'code' => $code,
                'message' => $message,
            ],
        );

        return CarnivalStepResult::failed(
            1800.0,
            "{$days} 天档领奖失败 {$code} -> {$displayMessage}",
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
