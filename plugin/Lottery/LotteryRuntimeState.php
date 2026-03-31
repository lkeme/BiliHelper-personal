<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

final class LotteryRuntimeState
{
    /**
     * @param array<string, array<int|string, mixed>> $state
     */
    public static function bootstrap(array $state): self
    {
        return new self(array_merge((new LotteryStateStore())->defaults(), $state));
    }

    /**
     * @param array<string, array<int|string, mixed>> $state
     */
    public function __construct(private array $state)
    {
    }

    /**
     * @return array<string, array<int|string, mixed>>
     */
    public function all(): array
    {
        return $this->state;
    }

    public function shiftPendingCv(): ?int
    {
        $cv = array_shift($this->state['wait_cv_list']);

        return is_int($cv) ? $cv : null;
    }

    public function popPendingDynamic(): ?int
    {
        $dynamic = array_pop($this->state['wait_dynamic_list']);

        return is_int($dynamic) ? $dynamic : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function shiftPendingLottery(): ?array
    {
        $lottery = array_shift($this->state['wait_lottery_list']);

        return is_array($lottery) ? $lottery : null;
    }

    public function hasCv(int $cv): bool
    {
        return in_array($cv, $this->state['cv_list'], true);
    }

    public function hasDynamic(int $dynamic): bool
    {
        return in_array($dynamic, $this->state['dynamic_list'], true);
    }

    public function addCv(int $cv): void
    {
        if ($this->hasCv($cv)) {
            return;
        }

        $this->state['cv_list'][] = $cv;
        $this->state['wait_cv_list'][] = $cv;
    }

    public function addDynamic(int $dynamic): void
    {
        if ($this->hasDynamic($dynamic)) {
            return;
        }

        $this->state['dynamic_list'][] = $dynamic;
        $this->state['wait_dynamic_list'][] = $dynamic;
    }

    /**
     * @param array<string, mixed> $lottery
     */
    public function addLottery(array $lottery): void
    {
        $key = "rid{$lottery['rid']}";
        if (array_key_exists($key, $this->state['lottery_list'])) {
            return;
        }

        $this->state['lottery_list'][$key] = $lottery;
        $this->state['wait_lottery_list'][$key] = $lottery;
    }

    public function pendingCvCount(): int
    {
        return count($this->state['wait_cv_list']);
    }

    public function pendingDynamicCount(): int
    {
        return count($this->state['wait_dynamic_list']);
    }

    public function pendingLotteryCount(): int
    {
        return count($this->state['wait_lottery_list']);
    }
}
