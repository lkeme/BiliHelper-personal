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
        $payload = $node->payload();
        $flowContext = $flow->context()->toArray();
        $drawResults = $this->normalizeDrawResults(
            $this->resolveStateValue($flowContext, $payload, 'draw_results', []),
        );
        $wins = [];
        foreach ($drawResults as $result) {
            $normalized = $this->normalizeSingleDrawResult($result);
            $giftId = (int)($normalized['gift_id'] ?? 0);
            $giftName = trim((string)($normalized['gift_name'] ?? ''));
            if (!$this->isWinningDrawResult($normalized, $giftName)) {
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

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeSingleDrawResult(array $result): array
    {
        $giftName = trim((string)($result['gift_name'] ?? ''));
        $giftId = array_key_exists('gift_id', $result)
            ? (int)$result['gift_id']
            : 0;
        $awardInfo = is_array($result['award_info'] ?? null)
            ? $result['award_info']
            : [];

        if ($giftName === '') {
            $giftName = trim((string)($awardInfo['name'] ?? ''));
        }
        if (!array_key_exists('gift_id', $result) && array_key_exists('id', $awardInfo)) {
            $giftId = (int)$awardInfo['id'];
        }
        if ($giftName === '' && trim((string)($result['award_sid'] ?? '')) === '' && $awardInfo === []) {
            $giftName = '未中奖';
        }

        $result['gift_id'] = $giftId;
        $result['gift_name'] = $giftName;
        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function isWinningDrawResult(array $result, string $giftName): bool
    {
        if ($giftName === '' || str_contains($giftName, '未中奖')) {
            return false;
        }

        $awardSid = trim((string)($result['award_sid'] ?? ''));
        if ($awardSid !== '') {
            return true;
        }

        if (is_array($result['award_info'] ?? null)) {
            return true;
        }

        return array_key_exists('gift_id', $result) && (int)$result['gift_id'] !== 0;
    }
}
