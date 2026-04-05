<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\EraTaskGateway;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot;

final class FinalClaimRewardNodeRunner implements NodeRunnerInterface
{
    public function __construct(
        private readonly EraTaskGateway $taskGateway,
    ) {
    }

    public function type(): string
    {
        return 'final_claim_reward';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $context = $flow->context()->toArray();
        $taskRows = $context['era_page_snapshot']['tasks'] ?? [];
        if (!is_array($taskRows)) {
            return new ActivityNodeResult(true, '没有可领奖的任务快照', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
            ], $now);
        }

        $claimedCount = 0;
        $skippedTaskIds = [];
        foreach ($taskRows as $taskRow) {
            if (!is_array($taskRow)) {
                continue;
            }

            $task = EraTaskSnapshot::fromArray($taskRow);
            if ($task->taskId() === '' || $task->taskAwardType() !== 1) {
                continue;
            }

            $infoResponse = $this->taskGateway->taskInfo($task->taskId());
            $mission = is_array($infoResponse['data'] ?? null) ? $infoResponse['data'] : null;
            if ((int)($infoResponse['code'] ?? -1) !== 0 || $mission === null) {
                return new ActivityNodeResult(false, '尾部领奖查询失败', [
                    'node_status' => ActivityNodeStatus::FAILED,
                ], $now);
            }

            $status = (int)($mission['status'] ?? 0);
            if ($status === 6) {
                continue;
            }
            if ($status === 1) {
                $skippedTaskIds[] = $task->taskId();
                continue;
            }
            if ($status !== 2) {
                continue;
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
                $skippedTaskIds[] = $task->taskId();
                continue;
            }
            if ($code !== 0) {
                return new ActivityNodeResult(false, '尾部领奖执行失败', [
                    'node_status' => ActivityNodeStatus::FAILED,
                ], $now);
            }

            $claimedCount++;
        }

        return new ActivityNodeResult(true, '尾部领奖处理完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => [
                'final_claim_summary' => [
                    'claimed_count' => $claimedCount,
                    'skipped_task_ids' => $skippedTaskIds,
                ],
            ],
        ], $now);
    }
}

