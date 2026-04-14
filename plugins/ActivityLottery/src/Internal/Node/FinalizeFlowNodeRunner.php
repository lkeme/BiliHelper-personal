<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;

final class FinalizeFlowNodeRunner implements NodeRunnerInterface
{
    /**
     * 获取类型标识
     * @return string
     */
    public function type(): string
    {
        return 'finalize_flow';
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
        return new ActivityNodeResult(true, '活动流收尾完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'flow_status' => ActivityFlowStatus::COMPLETED,
            'context_patch' => [
                'finalized_at' => $now,
            ],
        ], $now);
    }
}


