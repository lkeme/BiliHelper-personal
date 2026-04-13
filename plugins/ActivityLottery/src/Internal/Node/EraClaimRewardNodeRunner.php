<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\EraTaskGateway;

final class EraClaimRewardNodeRunner implements NodeRunnerInterface
{
    private const RETRY_DELAY_SECONDS = 300;

    public function __construct(
        private readonly EraTaskGateway $taskGateway,
    ) {
    }

    public function type(): string
    {
        return 'era_task_claim_reward';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if ($taskView->taskId() === '' || $task === null) {
            return new ActivityNodeResult(true, '领奖任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $infoResponse = $this->taskGateway->taskInfo($task->taskId());
        $mission = is_array($infoResponse['data'] ?? null) ? $infoResponse['data'] : null;
        $infoCode = (int)($infoResponse['code'] ?? -1);
        if ($infoCode === -500) {
            return new ActivityNodeResult(false, '领奖信息获取失败，稍后重试', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }
        if ($infoCode !== 0 || $mission === null) {
            return new ActivityNodeResult(false, '领奖信息获取失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $status = (int)($mission['status'] ?? 0);
        if ($status === 6) {
            return new ActivityNodeResult(true, '奖励已领取', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
                'context_patch' => $taskView->patchTaskRuntime([
                    'claim_reward_status' => 'already_claimed',
                ]),
            ], $now);
        }

        if ($status === 1) {
            return new ActivityNodeResult(true, '奖励领取需要额外账号绑定，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
                'context_patch' => $taskView->patchTaskRuntime([
                    'claim_reward_status' => 'binding_required',
                ]),
            ], $now);
        }

        $payload = [
            'act_id' => trim((string)($mission['act_id'] ?? '')),
            'act_name' => trim((string)($mission['act_name'] ?? '')),
            'task_name' => trim((string)($mission['task_name'] ?? $task->taskName())),
            'reward_name' => trim((string)($mission['reward_info']['award_name'] ?? $task->awardName())),
        ];
        $receiveResponse = $this->taskGateway->receiveReward($task->taskId(), $payload);
        $code = (int)($receiveResponse['code'] ?? -1);
        if ($code === 202100) {
            return new ActivityNodeResult(true, '奖励领取触发风控验证，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
                'context_patch' => $taskView->patchTaskRuntime([
                    'claim_reward_status' => 'risk_control',
                ]),
            ], $now);
        }
        if ($code === -500) {
            return new ActivityNodeResult(false, '奖励领取失败，稍后重试', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }
        if ($code !== 0) {
            return new ActivityNodeResult(false, '奖励领取失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        return new ActivityNodeResult(true, '奖励领取成功', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $taskView->patchTaskRuntime([
                'claim_reward_status' => 'claimed',
            ]),
        ], $now);
    }
}

