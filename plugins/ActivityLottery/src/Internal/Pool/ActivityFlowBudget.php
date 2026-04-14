<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Pool;

use RuntimeException;

final class ActivityFlowBudget
{
    /**
     * 初始化 ActivityFlowBudget
     * @param int $maxFlowsPerTick
     * @param int $maxStepsPerTick
     * @param int $maxRuntimeMsPerTick
     */
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

    /**
     * 处理maxFlowsPerTick
     * @return int
     */
    public function maxFlowsPerTick(): int
    {
        return $this->maxFlowsPerTick;
    }

    /**
     * 处理maxStepsPerTick
     * @return int
     */
    public function maxStepsPerTick(): int
    {
        return $this->maxStepsPerTick;
    }

    /**
     * 处理max运行时MsPerTick
     * @return int
     */
    public function maxRuntimeMsPerTick(): int
    {
        return $this->maxRuntimeMsPerTick;
    }

    /**
     * 处理max流程SelectionsPerTick
     * @return int
     */
    public function maxFlowSelectionsPerTick(): int
    {
        return min($this->maxFlowsPerTick, $this->maxStepsPerTick);
    }
}


