<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Pool;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use InvalidArgumentException;
use RuntimeException;

final class ActivityFlowPool
{
    private ?int $activeTickStartedAtMs = null;
    /**
     * @var array<string, true>
     */
    private array $pickedFlowIdsInTick = [];
    private int $selectedFlowCountInTick = 0;
    private int $selectedStepCountInTick = 0;
    private int $pendingReservedStepCountInTick = 0;
    private float $accountedRuntimeMsInTick = 0.0;

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
    public function pick(array $flows, int $now, int $tickStartedAtMs): array
    {
        $this->assertValidFlows($flows);

        $nowMs = microtime(true) * 1000;
        if (!$this->canContinue($tickStartedAtMs, $nowMs)) {
            return [];
        }

        if ($flows === []) {
            return [];
        }

        $eligible = array_values(array_filter(
            $flows,
            fn (ActivityFlow $flow): bool => $this->isFlowEligible($flow, $now)
                && !isset($this->pickedFlowIdsInTick[$flow->id()]),
        ));
        $eligible = $this->deduplicateFlowsById($eligible);
        if ($eligible === []) {
            return [];
        }

        $remainingFlowCount = $this->budget->maxFlowsPerTick() - $this->selectedFlowCountInTick;
        $remainingStepCount = $this->budget->maxStepsPerTick() - $this->selectedStepCountInTick;
        $pickLimit = min($remainingFlowCount, $remainingStepCount);
        if ($pickLimit <= 0) {
            return [];
        }

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
            $this->selectedFlowCountInTick++;
            $this->selectedStepCountInTick++;
            $this->pendingReservedStepCountInTick++;
        }

        return $selected;
    }

    public function noteStepExecuted(int $tickStartedAtMs, string $flowId, float $elapsedMs): void
    {
        $flowId = trim($flowId);
        if ($flowId === '') {
            throw new InvalidArgumentException('flowId 不能为空');
        }
        if (!is_finite($elapsedMs) || $elapsedMs < 0) {
            throw new InvalidArgumentException('elapsedMs 必须是大于等于 0 的有限数字');
        }

        $this->prepareTickState($tickStartedAtMs);
        if (!isset($this->pickedFlowIdsInTick[$flowId])) {
            $this->pickedFlowIdsInTick[$flowId] = true;
            $this->selectedFlowCountInTick++;
        }

        if ($this->pendingReservedStepCountInTick > 0) {
            $this->pendingReservedStepCountInTick--;
        } else {
            $this->selectedStepCountInTick++;
        }
        $this->accountedRuntimeMsInTick += $elapsedMs;
    }

    public function canContinue(int $tickStartedAtMs, float $nowMs): bool
    {
        $this->prepareTickState($tickStartedAtMs);

        if (($nowMs - $tickStartedAtMs) >= $this->budget->maxRuntimeMsPerTick()) {
            return false;
        }
        if ($this->accountedRuntimeMsInTick >= $this->budget->maxRuntimeMsPerTick()) {
            return false;
        }
        if ($this->selectedFlowCountInTick >= $this->budget->maxFlowsPerTick()) {
            return false;
        }
        if ($this->selectedStepCountInTick >= $this->budget->maxStepsPerTick()) {
            return false;
        }

        return true;
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
        $contracts = ActivityFlowPlanner::nodeTypeContracts();
        if (!isset($contracts[$node->type()])) {
            throw new RuntimeException(sprintf('未知 node type: %s', $node->type()));
        }
        $contract = $contracts[$node->type()];
        $defaultLane = $contract['default_lane'];

        $payloadLaneRaw = $node->payload()['lane'] ?? null;
        if ($payloadLaneRaw !== null && !is_scalar($payloadLaneRaw)) {
            throw new RuntimeException(sprintf(
                'lane 与 node type 契约冲突: node_type=%s expected=%s actual=%s',
                $node->type(),
                $defaultLane,
                get_debug_type($payloadLaneRaw),
            ));
        }
        $payloadLane = trim((string)($payloadLaneRaw ?? ''));
        if ($payloadLane === '') {
            return $defaultLane;
        }
        if (in_array($payloadLane, $contract['allowed_lanes'], true)) {
            return $payloadLane;
        }

        throw new RuntimeException(sprintf(
            'lane 与 node type 契约冲突: node_type=%s expected=%s actual=%s',
            $node->type(),
            implode('|', $contract['allowed_lanes']),
            $payloadLane,
        ));
    }

    /**
     * @param ActivityFlow[] $flows
     * @return ActivityFlow[]
     */
    private function deduplicateFlowsById(array $flows): array
    {
        $deduplicated = [];
        $seenIds = [];
        foreach ($flows as $flow) {
            $flowId = $flow->id();
            if (isset($seenIds[$flowId])) {
                continue;
            }

            $seenIds[$flowId] = true;
            $deduplicated[] = $flow;
        }

        return $deduplicated;
    }

    private function prepareTickState(int $tickStartedAtMs): void
    {
        if ($this->activeTickStartedAtMs === $tickStartedAtMs) {
            return;
        }

        $this->activeTickStartedAtMs = $tickStartedAtMs;
        $this->pickedFlowIdsInTick = [];
        $this->selectedFlowCountInTick = 0;
        $this->selectedStepCountInTick = 0;
        $this->pendingReservedStepCountInTick = 0;
        $this->accountedRuntimeMsInTick = 0.0;
    }

    /**
     * @param mixed[] $flows
     */
    private function assertValidFlows(array $flows): void
    {
        foreach ($flows as $index => $flow) {
            if ($flow instanceof ActivityFlow) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'flows[%d] 必须是 %s，实际为 %s',
                $index,
                ActivityFlow::class,
                get_debug_type($flow),
            ));
        }
    }
}
