<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\DynamicLottery;

final class DynamicLotteryRuntimeState
{
    /**
     * @param array<string, mixed> $state
     */
    public static function bootstrap(array $state): self
    {
        return new self(array_replace(DynamicLotteryStateStore::defaults(), $state));
    }

    /**
     * @param array<string, mixed> $state
     */
    public function __construct(private array $state)
    {
        $this->state['biz_date'] = trim((string)($this->state['biz_date'] ?? ''));
        $this->state['source_synced'] = (bool)($this->state['source_synced'] ?? false);
        $this->state['source_cv_id'] = max(0, (int)($this->state['source_cv_id'] ?? 0));
        $this->state['dynamic_list'] = $this->normalizeIntList($this->state['dynamic_list'] ?? []);
        $this->state['wait_dynamic_list'] = $this->normalizeIntList($this->state['wait_dynamic_list'] ?? []);
        $this->state['lottery_list'] = $this->normalizeLotteryMap($this->state['lottery_list'] ?? []);
        $this->state['wait_lottery_list'] = $this->normalizeLotteryMap($this->state['wait_lottery_list'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->state;
    }

    public function bizDate(): string
    {
        return (string)$this->state['biz_date'];
    }

    public function sourceSynced(): bool
    {
        return (bool)$this->state['source_synced'];
    }

    public function resetForBizDate(string $bizDate): void
    {
        if ($this->bizDate() === $bizDate) {
            return;
        }

        $this->state = DynamicLotteryStateStore::defaults();
        $this->state['biz_date'] = $bizDate;
    }

    /**
     * @param int[] $dynamicIds
     */
    public function seedDynamicQueue(?int $sourceCvId, array $dynamicIds): void
    {
        if ($this->sourceSynced()) {
            return;
        }

        $normalized = $this->normalizeIntList($dynamicIds);
        shuffle($normalized);

        $this->state['source_synced'] = true;
        $this->state['source_cv_id'] = $sourceCvId ?? 0;
        $this->state['dynamic_list'] = $normalized;
        $this->state['wait_dynamic_list'] = $normalized;
        $this->state['lottery_list'] = [];
        $this->state['wait_lottery_list'] = [];
    }

    public function shiftPendingDynamic(): ?int
    {
        $dynamic = array_shift($this->state['wait_dynamic_list']);

        return is_int($dynamic) ? $dynamic : null;
    }

    public function requeueDynamic(int $dynamicId): void
    {
        if ($dynamicId <= 0 || in_array($dynamicId, $this->state['wait_dynamic_list'], true)) {
            return;
        }

        $this->state['wait_dynamic_list'] = [$dynamicId, ...$this->state['wait_dynamic_list']];
        if (!in_array($dynamicId, $this->state['dynamic_list'], true)) {
            $this->state['dynamic_list'][] = $dynamicId;
        }
    }

    public function totalDynamicCount(): int
    {
        return count($this->state['dynamic_list']);
    }

    public function pendingDynamicCount(): int
    {
        return count($this->state['wait_dynamic_list']);
    }

    public function processedDynamicCount(): int
    {
        return max(0, $this->totalDynamicCount() - $this->pendingDynamicCount());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function shiftPendingLottery(): ?array
    {
        $lottery = array_shift($this->state['wait_lottery_list']);

        return is_array($lottery) ? $lottery : null;
    }

    /**
     * @param array<string, mixed> $lottery
     */
    public function addLottery(array $lottery): void
    {
        $key = 'rid' . $lottery['rid'];
        if (array_key_exists($key, $this->state['lottery_list'])) {
            return;
        }

        $this->state['lottery_list'][$key] = $lottery;
        $this->state['wait_lottery_list'][$key] = $lottery;
    }

    /**
     * @param array<string, mixed> $lottery
     */
    public function requeueLottery(array $lottery): void
    {
        $key = 'rid' . $lottery['rid'];
        if (!array_key_exists($key, $this->state['lottery_list'])) {
            $this->state['lottery_list'][$key] = $lottery;
        }

        if (array_key_exists($key, $this->state['wait_lottery_list'])) {
            return;
        }

        $this->state['wait_lottery_list'][$key] = $lottery;
    }

    public function pendingLotteryCount(): int
    {
        return count($this->state['wait_lottery_list']);
    }

    public function hasWork(): bool
    {
        return $this->pendingDynamicCount() > 0 || $this->pendingLotteryCount() > 0;
    }

    /**
     * @param mixed $value
     * @return int[]
     */
    private function normalizeIntList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            $id = (int)$item;
            if ($id > 0 && !in_array($id, $list, true)) {
                $list[] = $id;
            }
        }

        return array_values($list);
    }

    /**
     * @param mixed $value
     * @return array<string, array<string, mixed>>
     */
    private function normalizeLotteryMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && is_array($item)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }
}
