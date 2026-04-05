<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\ActivityLotteryGateway;
use Throwable;

final class LoadActivitySnapshotNodeRunner implements NodeRunnerInterface
{
    public function __construct(
        private readonly ActivityLotteryGateway $activityGateway = new ActivityLotteryGateway(),
    ) {
    }

    public function type(): string
    {
        return 'load_activity_snapshot';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $activity = $flow->activity();
        $url = trim((string)($activity['url'] ?? ''));
        if ($url === '') {
            return new ActivityNodeResult(false, '缺少活动页 URL', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        try {
            $html = trim($this->activityGateway->fetchActivityPageHtml($url));
        } catch (Throwable $e) {
            return new ActivityNodeResult(false, '活动页加载失败: ' . $e->getMessage(), [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        if ($html === '') {
            return new ActivityNodeResult(false, '活动页为空', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        return new ActivityNodeResult(true, '活动页快照加载成功', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => [
                'activity_snapshot' => [
                    'url' => $url,
                    'html' => $html,
                    'fetched_at' => $now,
                ],
            ],
        ], $now);
    }
}


