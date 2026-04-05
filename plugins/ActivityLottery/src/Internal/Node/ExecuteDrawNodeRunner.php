<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\DrawGateway;

final class ExecuteDrawNodeRunner implements NodeRunnerInterface
{
    public function __construct(
        private readonly DrawGateway $drawGateway,
    ) {
    }

    public function type(): string
    {
        return 'execute_draw';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $flowContext = $flow->context()->toArray();
        $remaining = (int)$this->resolveStateValue($flowContext, 'draw_times_remaining', 0);
        $drawResults = $this->normalizeDrawResults(
            $this->resolveStateValue($flowContext, 'draw_results', []),
        );

        if ($remaining <= 0) {
            return new ActivityNodeResult(true, '抽奖次数已耗尽', [
                'node_status' => ActivityNodeStatus::SKIPPED,
                'context_patch' => [
                    'draw_times_remaining' => 0,
                    'draw_results' => $drawResults,
                ],
            ], $now);
        }

        $activity = ResolvedActivityView::fromFlow($flow)->toActivityArray();
        $drawCount = $this->resolveDrawCount($remaining);
        $response = $this->drawGateway->drawOnce($activity, $drawCount);
        $code = (int)($response['code'] ?? -1);
        if ($code === 170415) {
            return new ActivityNodeResult(true, '执行抽奖结束：剩余抽奖次数不足', [
                'node_status' => ActivityNodeStatus::SKIPPED,
                'draw_execute_response' => $response,
                'context_patch' => [
                    'draw_times_remaining' => 0,
                    'draw_results' => $drawResults,
                    'draw_batch_size' => 0,
                    'draw_batch_win_count' => 0,
                    'draw_batch_win_names' => [],
                    'draw_execute_response' => $response,
                ],
            ], $now);
        }
        if ($code !== 0) {
            return new ActivityNodeResult(false, '执行抽奖失败', [
                'node_status' => ActivityNodeStatus::FAILED,
                'draw_execute_response' => $response,
                'context_patch' => [
                    'draw_times_remaining' => $remaining,
                    'draw_results' => $drawResults,
                    'draw_batch_size' => 0,
                    'draw_batch_win_count' => 0,
                    'draw_batch_win_names' => [],
                ],
            ], $now);
        }

        $batchResults = $this->normalizeResponseResults($response);
        $latest = $batchResults === []
            ? []
            : $batchResults[count($batchResults) - 1];
        $giftName = trim((string)($latest['gift_name'] ?? ''));
        if ($giftName === '') {
            return new ActivityNodeResult(false, '执行抽奖失败：返回结果缺少奖品名', [
                'node_status' => ActivityNodeStatus::FAILED,
                'draw_execute_response' => $response,
                'context_patch' => [
                    'draw_times_remaining' => $remaining,
                    'draw_results' => $drawResults,
                    'draw_batch_size' => 0,
                    'draw_batch_win_count' => 0,
                    'draw_batch_win_names' => [],
                    'draw_execute_response' => $response,
                ],
            ], $now);
        }

        $drawResults = array_merge($drawResults, $batchResults);
        $actualDrawCount = count($batchResults);
        $remaining = max(0, $remaining - $actualDrawCount);
        $nodeStatus = $remaining > 0 ? ActivityNodeStatus::WAITING : ActivityNodeStatus::SUCCEEDED;
        $batchWins = array_values(array_filter($batchResults, static function (array $result): bool {
            $giftName = trim((string)($result['gift_name'] ?? ''));
            if ($giftName === '' || str_contains($giftName, '未中奖')) {
                return false;
            }

            $awardSid = trim((string)($result['award_sid'] ?? ''));
            if ($awardSid !== '') {
                return true;
            }

            return is_array($result['award_info'] ?? null);
        }));
        $batchWinNames = array_values(array_filter(array_map(
            static fn (array $result): string => trim((string)($result['gift_name'] ?? '')),
            $batchWins,
        )));

        $resultPayload = [
            'node_status' => $nodeStatus,
            'context_patch' => [
                'draw_times_remaining' => $remaining,
                'draw_results' => $drawResults,
                'draw_batch_size' => $actualDrawCount,
                'draw_batch_win_count' => count($batchWins),
                'draw_batch_win_names' => $batchWinNames,
                'last_draw_result' => $latest,
                'draw_execute_response' => $response,
            ],
        ];
        if ($nodeStatus === ActivityNodeStatus::WAITING) {
            $resultPayload['next_run_at'] = $now + 5;
        }

        return new ActivityNodeResult(true, '执行抽奖成功', $resultPayload, $now);
    }

    private function resolveDrawCount(int $remaining): int
    {
        return $remaining >= 10 ? 10 : 1;
    }

    /**
     * @param array<string, mixed> $flowContext
     */
    private function resolveStateValue(array $flowContext, string $key, mixed $default): mixed
    {
        if (array_key_exists($key, $flowContext)) {
            return $flowContext[$key];
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
     * @param array<string, mixed> $response
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResponseResults(array $response): array
    {
        $results = [];
        foreach (is_array($response['data'] ?? null) ? $response['data'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $results[] = $this->normalizeDrawResult($item);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeDrawResult(array $result): array
    {
        if ($result === []) {
            return [];
        }

        $giftId = (int)($result['gift_id'] ?? 0);
        $giftName = trim((string)($result['gift_name'] ?? ''));
        if ($giftName !== '') {
            if ($giftId <= 0 && str_contains($giftName, '未中奖')) {
                $giftName = '未中奖';
            }

            $result['gift_id'] = $giftId;
            $result['gift_name'] = $giftName;
            return $result;
        }

        $awardInfo = is_array($result['award_info'] ?? null)
            ? $result['award_info']
            : [];
        $awardSid = trim((string)($result['award_sid'] ?? ''));
        $giftId = (int)($awardInfo['id'] ?? 0);
        $giftName = trim((string)($awardInfo['name'] ?? ''));
        if (
            $giftName === ''
            && $awardSid === ''
            && $awardInfo === []
            && (array_key_exists('award_sid', $result) || array_key_exists('award_info', $result))
        ) {
            $giftName = '未中奖';
        }

        $result['gift_id'] = $giftId;
        $result['gift_name'] = $giftName;
        return $result;
    }
}

