<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Pool;

use RuntimeException;

final class ActivityFlowBudget
{
    public function __construct(
        private readonly int $maxFlowsPerTick,
        private readonly int $maxStepsPerTick,
        private readonly int $maxRuntimeMsPerTick,
    ) {
        if ($this->maxFlowsPerTick <= 0) {
            throw new RuntimeException('max_flows_per_tick 必须大于 0');
        }
        if ($this->maxStepsPerTick <= 0) {
            throw new RuntimeException('max_steps_per_tick 必须大于 0');
        }
        if ($this->maxRuntimeMsPerTick <= 0) {
            throw new RuntimeException('max_runtime_ms_per_tick 必须大于 0');
        }
    }

    public function maxFlowsPerTick(): int
    {
        return $this->maxFlowsPerTick;
    }

    public function maxStepsPerTick(): int
    {
        return $this->maxStepsPerTick;
    }

    public function maxRuntimeMsPerTick(): int
    {
        return $this->maxRuntimeMsPerTick;
    }

    public function maxFlowSelectionsPerTick(): int
    {
        return min($this->maxFlowsPerTick, $this->maxStepsPerTick);
    }
}


