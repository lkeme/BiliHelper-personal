<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LiveReservation;

final class LiveReservationRuntimeState
{
    /**
     * @param array<string, mixed> $state
     */
    public static function bootstrap(array $state): self
    {
        return new self(array_replace(LiveReservationStateStore::defaults(), $state));
    }

    /**
     * @param array<string, mixed> $state
     */
    public function __construct(private array $state)
    {
        $this->state['biz_date'] = trim((string)($this->state['biz_date'] ?? ''));
        $this->state['source_synced'] = (bool)($this->state['source_synced'] ?? false);
        $this->state['source_cv_id'] = max(0, (int)($this->state['source_cv_id'] ?? 0));
        $this->state['up_mid_list'] = $this->normalizeStringList($this->state['up_mid_list'] ?? []);
        $this->state['wait_up_mid_list'] = $this->normalizeStringList($this->state['wait_up_mid_list'] ?? []);
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

        $this->state = LiveReservationStateStore::defaults();
        $this->state['biz_date'] = $bizDate;
    }

    /**
     * @param string[] $articleUpMids
     * @param string[] $configuredUpMids
     */
    public function seedUpMidQueue(?int $sourceCvId, array $articleUpMids, array $configuredUpMids): void
    {
        if ($this->sourceSynced()) {
            return;
        }

        $merged = $this->normalizeStringList(array_merge($articleUpMids, $configuredUpMids));
        shuffle($merged);

        $this->state['source_synced'] = true;
        $this->state['source_cv_id'] = $sourceCvId ?? 0;
        $this->state['up_mid_list'] = $merged;
        $this->state['wait_up_mid_list'] = $merged;
    }

    public function shiftPendingUpMid(): ?string
    {
        $upMid = array_shift($this->state['wait_up_mid_list']);

        return is_string($upMid) && $upMid !== '' ? $upMid : null;
    }

    public function pendingUpMidCount(): int
    {
        return count($this->state['wait_up_mid_list']);
    }

    public function hasWork(): bool
    {
        return $this->pendingUpMidCount() > 0;
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            $normalized = trim((string)$item);
            if ($normalized !== '' && !in_array($normalized, $list, true)) {
                $list[] = $normalized;
            }
        }

        return array_values($list);
    }
}
