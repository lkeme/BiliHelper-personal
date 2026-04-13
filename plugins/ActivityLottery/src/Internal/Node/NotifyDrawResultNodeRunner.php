<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\ActivityLotteryGateway;
use Bhp\Util\Exceptions\RequestException;
use Throwable;

final class NotifyDrawResultNodeRunner implements NodeRunnerInterface
{
    private const AWARD_RECORD_URL_TEMPLATE = 'https://www.bilibili.com/blackboard/era/new-award-record.html?activity_id=%s';
    private const RETRY_DELAY_SECONDS = 300;

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
            $message = $this->buildNoticeMessage($flow, $wins, $winCount);

            try {
                $this->activityGateway->pushNotice('activity_lottery', $message);
            } catch (RequestException $e) {
                return new ActivityNodeResult(false, '推送抽奖通知失败: ' . $e->getMessage(), [
                    'node_status' => ActivityNodeStatus::WAITING,
                    'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                ], $now);
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

    /**
     * @param array<int, mixed> $wins
     */
    private function buildNoticeMessage(ActivityFlow $flow, array $wins, int $winCount): string
    {
        $activity = ResolvedActivityView::fromFlow($flow);
        $activityTitle = $activity->title() !== '' ? $activity->title() : '未命名活动';
        $rewardSummary = $this->summarizeRewardNames($wins);
        if ($rewardSummary === []) {
            $rewardSummary = [['name' => '未知奖品', 'count' => max(1, $winCount)]];
        }

        $lines = [
            '活动抽奖命中',
            sprintf('活动: %s', $activityTitle),
            sprintf('命中次数: %d', $winCount),
            '奖品清单:',
        ];

        foreach ($rewardSummary as $index => $reward) {
            $lines[] = sprintf(
                '%d. %s x%d',
                $index + 1,
                $reward['name'],
                $reward['count'],
            );
        }

        $activityUrl = $activity->url();
        if ($activityUrl !== '') {
            $lines[] = '活动地址:';
            $lines[] = $activityUrl;
        }

        $activityId = $activity->activityId();
        if ($activityId !== '') {
            $lines[] = '中奖记录:';
            $lines[] = sprintf(self::AWARD_RECORD_URL_TEMPLATE, rawurlencode($activityId));
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, mixed> $wins
     * @return list<array{name: string, count: int}>
     */
    private function summarizeRewardNames(array $wins): array
    {
        $summary = [];
        $order = [];
        foreach ($wins as $win) {
            if (!is_array($win)) {
                continue;
            }

            $rewardName = trim((string)($win['gift_name'] ?? ''));
            if ($rewardName === '') {
                continue;
            }

            if (!isset($summary[$rewardName])) {
                $summary[$rewardName] = 0;
                $order[] = $rewardName;
            }
            $summary[$rewardName]++;
        }

        $result = [];
        foreach ($order as $rewardName) {
            $result[] = [
                'name' => $rewardName,
                'count' => $summary[$rewardName],
            ];
        }

        return $result;
    }
}

