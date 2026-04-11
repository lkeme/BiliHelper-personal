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
        $this->state['reservation_queue'] = $this->normalizeReservationQueue($this->state['reservation_queue'] ?? []);
        $this->state['reservation_keys'] = $this->normalizeStringList($this->state['reservation_keys'] ?? []);
        $this->state['discovered_reservation_total'] = max(0, (int)($this->state['discovered_reservation_total'] ?? 0));
        $this->state['processed_reservation_count'] = max(0, (int)($this->state['processed_reservation_count'] ?? 0));
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

    public function totalUpMidCount(): int
    {
        return count($this->state['up_mid_list']);
    }

    public function processedUpMidCount(): int
    {
        return max(0, $this->totalUpMidCount() - $this->pendingUpMidCount());
    }

    public function pendingReservationCount(): int
    {
        return count($this->state['reservation_queue']);
    }

    public function discoveredReservationTotal(): int
    {
        return (int)$this->state['discovered_reservation_total'];
    }

    public function processedReservationCount(): int
    {
        return (int)$this->state['processed_reservation_count'];
    }

    public function incrementProcessedReservationCount(): void
    {
        $this->state['processed_reservation_count'] = $this->processedReservationCount() + 1;
    }

    /**
     * @param array<int, array<string, mixed>> $reservations
     * @return int 新增任务数
     */
    public function enqueueReservations(array $reservations): int
    {
        $added = 0;
        foreach ($reservations as $reservation) {
            if (!is_array($reservation)) {
                continue;
            }

            $sid = trim((string)($reservation['sid'] ?? ''));
            if ($sid === '' || in_array($sid, $this->state['reservation_keys'], true)) {
                continue;
            }

            $this->state['reservation_keys'][] = $sid;
            $this->state['reservation_queue'][] = $reservation;
            $added++;
        }

        if ($added > 0) {
            $this->state['discovered_reservation_total'] = $this->discoveredReservationTotal() + $added;
        }

        return $added;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function shiftPendingReservation(): ?array
    {
        $reservation = array_shift($this->state['reservation_queue']);

        return is_array($reservation) ? $reservation : null;
    }

    public function hasWork(): bool
    {
        return $this->pendingUpMidCount() > 0 || $this->pendingReservationCount() > 0;
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

    /**
     * @param mixed $value
     * @return array<int, array<string, mixed>>
     */
    private function normalizeReservationQueue(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $queue = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $queue[] = $item;
            }
        }

        return array_values($queue);
    }
}
