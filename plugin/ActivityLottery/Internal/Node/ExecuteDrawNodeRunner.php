<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\DrawGateway;

final class ExecuteDrawNodeRunner implements NodeRunnerInterface
{
    public function __construct(
        private readonly DrawGateway $drawGateway = new DrawGateway(),
    ) {
    }

    public function type(): string
    {
        return 'execute_draw';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $payload = $node->payload();
        $remaining = (int)($payload['draw_times_remaining'] ?? 0);
        $drawResults = $this->normalizeDrawResults($payload['draw_results'] ?? []);

        if ($remaining <= 0) {
            return new ActivityNodeResult(true, '抽奖次数已耗尽', [
                'node_status' => ActivityNodeStatus::SKIPPED,
                'context_patch' => [
                    'draw_times_remaining' => 0,
                    'draw_results' => $drawResults,
                ],
            ], $now);
        }

        $response = $this->drawGateway->drawOnce($flow->activity());
        $code = (int)($response['code'] ?? -1);
        if ($code !== 0) {
            return new ActivityNodeResult(false, '执行抽奖失败', [
                'node_status' => ActivityNodeStatus::FAILED,
                'draw_execute_response' => $response,
                'context_patch' => [
                    'draw_times_remaining' => $remaining,
                    'draw_results' => $drawResults,
                ],
            ], $now);
        }

        $latest = is_array($response['data'][0] ?? null) ? $response['data'][0] : [];
        $drawResults[] = $latest;
        $remaining = max(0, $remaining - 1);
        $nodeStatus = $remaining > 0 ? ActivityNodeStatus::WAITING : ActivityNodeStatus::SUCCEEDED;

        $resultPayload = [
            'node_status' => $nodeStatus,
            'context_patch' => [
                'draw_times_remaining' => $remaining,
                'draw_results' => $drawResults,
                'last_draw_result' => $latest,
                'draw_execute_response' => $response,
            ],
        ];
        if ($nodeStatus === ActivityNodeStatus::WAITING) {
            $resultPayload['next_run_at'] = $now + 5;
        }

        return new ActivityNodeResult(true, '执行抽奖成功', $resultPayload, $now);
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

