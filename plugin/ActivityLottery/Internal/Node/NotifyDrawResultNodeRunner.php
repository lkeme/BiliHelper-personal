<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\ActivityLotteryGateway;
use Throwable;

final class NotifyDrawResultNodeRunner implements NodeRunnerInterface
{
    public function __construct(
        private readonly ActivityLotteryGateway $activityGateway = new ActivityLotteryGateway(),
    ) {
    }

    public function type(): string
    {
        return 'notify_draw_result';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $payload = $node->payload();
        $flowContext = $flow->context()->toArray();
        $summaryValue = $this->resolveStateValue($flowContext, $payload, 'draw_summary', []);
        $summary = is_array($summaryValue)
            ? $summaryValue
            : [];
        $wins = is_array($summary['wins'] ?? null) ? $summary['wins'] : [];
        $winCount = (int)($summary['win_count'] ?? count($wins));
        $message = '';

        if ($winCount > 0) {
            $activityTitle = trim((string)($flow->activity()['title'] ?? '未命名活动'));
            $rewardNames = [];
            foreach ($wins as $win) {
                if (!is_array($win)) {
                    continue;
                }
                $rewardName = trim((string)($win['gift_name'] ?? ''));
                if ($rewardName !== '') {
                    $rewardNames[] = $rewardName;
                }
            }

            $message = sprintf(
                "活动抽奖命中\n活动: %s\n奖品: %s",
                $activityTitle,
                $rewardNames === [] ? '未知奖品' : implode(' / ', $rewardNames),
            );

            try {
                $this->activityGateway->pushNotice('activity_lottery', $message);
            } catch (Throwable $e) {
                return new ActivityNodeResult(false, '推送抽奖通知失败: ' . $e->getMessage(), [
                    'node_status' => ActivityNodeStatus::FAILED,
                ], $now);
            }
        }

        return new ActivityNodeResult(true, '抽奖通知处理完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => [
                'notice_sent' => $winCount > 0,
                'notice_message' => $message,
            ],
        ], $now);
    }

    /**
     * @param array<string, mixed> $flowContext
     * @param array<string, mixed> $nodePayload
     */
    private function resolveStateValue(array $flowContext, array $nodePayload, string $key, mixed $default): mixed
    {
        if (array_key_exists($key, $flowContext)) {
            return $flowContext[$key];
        }

        if (array_key_exists($key, $nodePayload)) {
            return $nodePayload[$key];
        }

        return $default;
    }
}
