<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;

final class RecordDrawResultNodeRunner implements NodeRunnerInterface
{
    public function type(): string
    {
        return 'record_draw_result';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $drawResults = $this->normalizeDrawResults($node->payload()['draw_results'] ?? []);
        $wins = [];
        foreach ($drawResults as $result) {
            $giftId = (int)($result['gift_id'] ?? 0);
            $giftName = trim((string)($result['gift_name'] ?? ''));
            if ($giftId <= 0 || str_contains($giftName, '未中奖')) {
                continue;
            }

            $wins[] = [
                'gift_id' => $giftId,
                'gift_name' => $giftName,
            ];
        }

        return new ActivityNodeResult(true, '抽奖结果记录完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => [
                'draw_summary' => [
                    'total_count' => count($drawResults),
                    'win_count' => count($wins),
                    'wins' => $wins,
                ],
            ],
        ], $now);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDrawResults(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }
}

