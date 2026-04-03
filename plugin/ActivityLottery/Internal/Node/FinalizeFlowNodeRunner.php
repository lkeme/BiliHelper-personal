<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;

final class FinalizeFlowNodeRunner implements NodeRunnerInterface
{
    public function type(): string
    {
        return 'finalize_flow';
    }

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

