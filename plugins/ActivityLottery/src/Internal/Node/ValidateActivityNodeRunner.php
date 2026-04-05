<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;

final class ValidateActivityNodeRunner implements NodeRunnerInterface
{
    public function type(): string
    {
        return 'validate_activity_window';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $activityView = ResolvedActivityView::fromFlow($flow);
        if (!$activityView->hasStableKey()) {
            return new ActivityNodeResult(false, '活动缺少稳定标识', [
                'node_status' => ActivityNodeStatus::FAILED,
                'flow_status' => ActivityFlowStatus::FAILED,
            ], $now);
        }

        $startTime = $activityView->startTime();
        $endTime = $activityView->endTime();

        if ($endTime > 0 && $endTime <= $now) {
            return new ActivityNodeResult(true, '活动已结束，跳过执行', [
                'node_status' => ActivityNodeStatus::SKIPPED,
                'flow_status' => ActivityFlowStatus::EXPIRED,
            ], $now);
        }

        if ($startTime > 0 && $startTime > $now) {
            return new ActivityNodeResult(true, '活动未开始，等待执行', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $startTime,
            ], $now);
        }

        return new ActivityNodeResult(true, '活动校验通过', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
        ], $now);
    }
}

