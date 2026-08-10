<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

use Bhp\Api\Api\X\Activity\ApiActivity;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Gateway\TaskProgressGateway;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\State\CarnivalStateStore;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\TaskStatus;

/**
 * 每日签到（R2）。
 *
 * 幂等是本 runner 的第一优先级：cycle 为 10-20 分钟，一天约 70 次 tick，
 * 无条件 POST 等于每天打 70 次写接口。
 *
 * 判据优先级：
 *   1. totalv2 的 task_status —— 权威
 *   2. 本地 signin_date —— totalv2 未返回该任务时的兜底
 *
 * 响应异常后复查签到状态（与项目 VipPoint 修复采用同一策略），
 * 避免接口返回非 0 但实际已签到时被误判为失败并触发熔断。
 */
final class SignInTaskRunner implements TaskRunnerInterface
{
    private readonly ApiActivity $apiActivity;
    private readonly TaskProgressGateway $taskProgressGateway;
    private readonly CarnivalStateStore $stateStore;
    private readonly AuthFailureClassifier $authFailureClassifier;

    /** @var \Closure(string, string, array<string, mixed>): void */
    private readonly \Closure $logger;

    /**
     * @param callable(string, string, array<string, mixed>): void $logger
     */
    public function __construct(
        ApiActivity $apiActivity,
        TaskProgressGateway $taskProgressGateway,
        CarnivalStateStore $stateStore,
        callable $logger,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiActivity = $apiActivity;
        $this->taskProgressGateway = $taskProgressGateway;
        $this->stateStore = $stateStore;
        $this->logger = \Closure::fromCallable($logger);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    public function key(): string
    {
        return 'sign_in';
    }

    public function run(CarnivalContext $context): CarnivalStepResult
    {
        $taskId = $context->snapshot->signInTaskId;
        $counter = $context->snapshot->signInCounter;
        if ($taskId === '' || $counter === '') {
            return CarnivalStepResult::skipped('签到: 缺少 task_id 或 counter，跳过');
        }

        $status = $context->taskStatus($taskId);
        if ($status === TaskStatus::FINISHED) {
            return CarnivalStepResult::skipped('签到: 任务已完结');
        }
        if ($status === TaskStatus::CLAIMABLE) {
            return CarnivalStepResult::skipped('签到: 有奖励待领取，交由领奖处理');
        }

        if ($this->stateStore->signedInOn($context->bizDate)) {
            return CarnivalStepResult::skipped('签到: 今日已签到');
        }

        $response = $this->apiActivity->sendPoints($taskId, $counter, $context->snapshot->activityUrl);
        $this->authFailureClassifier->assertNotAuthFailure($response, '次元奇旅签到时账号未登录');

        $code = (int)($response['code'] ?? -1);
        $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));

        if ($code === 0) {
            $this->stateStore->markSignedIn($context->bizDate);
            $this->log('notice', '次元奇旅: 签到成功', ['累计天数' => $this->currentDays($context, $taskId)]);

            return CarnivalStepResult::done('签到成功');
        }

        // 响应异常 → 复查真实签到状态，避免误判失败
        if ($this->recheckSignedIn($taskId)) {
            $this->stateStore->markSignedIn($context->bizDate);
            $this->log('info', '次元奇旅: 签到接口返回异常但复查已签到，视为完成', [
                'code' => $code,
                'message' => $message,
            ]);

            return CarnivalStepResult::skipped('签到: 复查确认今日已签到');
        }

        $this->log('warning', '次元奇旅: 签到失败', ['code' => $code, 'message' => $message]);

        return CarnivalStepResult::failed(1800.0, "签到失败 {$code} -> {$message}");
    }

    /**
     * 复查签到状态：task_status 变为可领奖/已完结，或进度已推进，均视为已签到
     */
    private function recheckSignedIn(string $taskId): bool
    {
        $snapshots = $this->taskProgressGateway->fetch([$taskId]);
        $detail = $snapshots[$taskId] ?? null;
        if (!is_array($detail)) {
            return false;
        }

        $status = TaskStatus::of($detail['task_status'] ?? null);
        if (in_array($status, [TaskStatus::CLAIMABLE, TaskStatus::FINISHED], true)) {
            return true;
        }

        $indicators = $detail['indicators'] ?? null;
        if (is_array($indicators) && is_array($indicators[0] ?? null)) {
            return max(0, (int)($indicators[0]['cur_value'] ?? 0)) > 0;
        }

        return false;
    }

    /**
     * 累计签到天数（accumulative_count 优先，退化到 indicators）
     */
    private function currentDays(CarnivalContext $context, string $taskId): int
    {
        $detail = $context->taskDetail($taskId);
        $accumulative = $detail['accumulative_count'] ?? null;
        if (is_numeric($accumulative) && (int)$accumulative > 0) {
            return (int)$accumulative;
        }

        return $context->taskCurrentValue($taskId);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
