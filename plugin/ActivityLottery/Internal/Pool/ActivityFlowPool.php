<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Pool;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use RuntimeException;

final class ActivityFlowPool
{
    private ?int $activeTickStartedAtMs = null;
    /**
     * @var array<string, true>
     */
    private array $pickedFlowIdsInTick = [];

    public function __construct(
        private readonly ActivityFlowBudget $budget,
        private readonly ActivityFlowPicker $picker = new ActivityFlowPicker(),
        private readonly ActivityLaneLimiter $laneLimiter = new ActivityLaneLimiter(),
    ) {
    }

    /**
     * @param ActivityFlow[] $flows
     * @return ActivityFlow[]
     */
    public function pick(array $flows, int $now, ?int $tickStartedAtMs = null): array
    {
        $tickStartedAtMs ??= (int)(microtime(true) * 1000);
        $nowMs = (int)(microtime(true) * 1000);
        if (($nowMs - $tickStartedAtMs) >= $this->budget->maxRuntimeMsPerTick()) {
            return [];
        }

        $this->prepareTickState($tickStartedAtMs);
        $eligible = array_values(array_filter(
            $flows,
            fn (mixed $flow): bool => $flow instanceof ActivityFlow
                && $this->isFlowEligible($flow, $now)
                && !isset($this->pickedFlowIdsInTick[$flow->id()]),
        ));
        if ($eligible === []) {
            return [];
        }

        $pickLimit = $this->budget->maxFlowSelectionsPerTick();
        $selected = [];
        $scanLimit = count($eligible);
        $scanned = 0;
        while ($scanned < $scanLimit && count($selected) < $pickLimit) {
            $candidateBatch = $this->picker->pick($eligible, 1);
            $scanned++;
            if ($candidateBatch === []) {
                break;
            }

            $flow = $candidateBatch[0];
            $lane = $this->resolveLane($flow);
            if (!$this->laneLimiter->canPass($lane, $now)) {
                continue;
            }

            $this->laneLimiter->reserve($lane, $now);
            $selected[] = $flow;
            $this->pickedFlowIdsInTick[$flow->id()] = true;
        }

        return $selected;
    }

    private function isFlowEligible(ActivityFlow $flow, int $now): bool
    {
        if (!in_array($flow->status(), [
            ActivityFlowStatus::PENDING,
            ActivityFlowStatus::RUNNING,
            ActivityFlowStatus::BLOCKED,
        ], true)) {
            return false;
        }

        if ($flow->nextRunAt() > $now) {
            return false;
        }

        return isset($flow->nodes()[$flow->currentNodeIndex()]);
    }

    private function resolveLane(ActivityFlow $flow): string
    {
        $node = $flow->nodes()[$flow->currentNodeIndex()];
        $payloadLane = trim((string)($node->payload()['lane'] ?? ''));
        if ($payloadLane !== '') {
            return $payloadLane;
        }

        return match ($node->type()) {
            'load_activity_snapshot', 'parse_era_page' => 'page_fetch',
            'refresh_draw_times' => 'draw_refresh',
            'execute_draw' => 'draw_execute',
            'claim_reward' => 'claim_reward',
            'validate_activity_window', 'finalize_flow' => 'task_status',
            default => throw new RuntimeException(sprintf('未知 node type: %s', $node->type())),
        };
    }

    private function prepareTickState(int $tickStartedAtMs): void
    {
        if ($this->activeTickStartedAtMs === $tickStartedAtMs) {
            return;
        }

        $this->activeTickStartedAtMs = $tickStartedAtMs;
        $this->pickedFlowIdsInTick = [];
    }
}
