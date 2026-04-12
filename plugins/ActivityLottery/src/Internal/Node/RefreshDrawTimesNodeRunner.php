<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\DrawGateway;

final class RefreshDrawTimesNodeRunner implements NodeRunnerInterface
{
    private const RETRY_DELAY_SECONDS = 300;

    public function __construct(
        private readonly DrawGateway $drawGateway,
    ) {
    }

    public function type(): string
    {
        return 'refresh_draw_times';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $activity = ResolvedActivityView::fromFlow($flow)->toActivityArray();
        $response = $this->drawGateway->refreshTimes($activity);
        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            return new ActivityNodeResult(false, '刷新抽奖次数失败，稍后重试', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                'draw_refresh_response' => $response,
            ], $now);
        }
        if ($code !== 0) {
            return new ActivityNodeResult(false, '刷新抽奖次数失败', [
                'node_status' => ActivityNodeStatus::FAILED,
                'draw_refresh_response' => $response,
            ], $now);
        }

        $times = (int)($response['data']['times'] ?? 0);
        if ($times <= 0) {
            return new ActivityNodeResult(true, '当前无可用抽奖次数', [
                'node_status' => ActivityNodeStatus::SKIPPED,
                'context_patch' => [
                    'draw_times_remaining' => 0,
                    'draw_results' => [],
                    'draw_times_refreshed_at' => $now,
                    'draw_refresh_response' => $response,
                ],
            ], $now);
        }

        return new ActivityNodeResult(true, '刷新抽奖次数成功', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => [
                'draw_times_remaining' => $times,
                'draw_results' => [],
                'draw_times_refreshed_at' => $now,
                'draw_refresh_response' => $response,
            ],
        ], $now);
    }
}

