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
        $this->state['current_batch_up_mid'] = trim((string)($this->state['current_batch_up_mid'] ?? ''));
        $this->state['current_batch_reservation_total'] = max(0, (int)($this->state['current_batch_reservation_total'] ?? 0));
        $this->state['current_batch_processed_reservation_count'] = max(0, (int)($this->state['current_batch_processed_reservation_count'] ?? 0));
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

    /**
     * 处理biz日期
     * @return string
     */
    public function bizDate(): string
    {
        return (string)$this->state['biz_date'];
    }

    /**
     * 处理来源Synced
     * @return bool
     */
    public function sourceSynced(): bool
    {
        return (bool)$this->state['source_synced'];
    }

    /**
     * 处理resetForBiz日期
     * @param string $bizDate
     * @return void
     */
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

    /**
     * 处理shift待处理UpMid
     * @return ?string
     */
    public function shiftPendingUpMid(): ?string
    {
        $upMid = array_shift($this->state['wait_up_mid_list']);

        return is_string($upMid) && $upMid !== '' ? $upMid : null;
    }

    /**
     * 处理requeueUpMid
     * @param string $upMid
     * @return void
     */
    public function requeueUpMid(string $upMid): void
    {
        $upMid = trim($upMid);
        if ($upMid === '') {
            return;
        }

        if (in_array($upMid, $this->state['wait_up_mid_list'], true)) {
            return;
        }

        array_unshift($this->state['wait_up_mid_list'], $upMid);
        if (!in_array($upMid, $this->state['up_mid_list'], true)) {
            array_unshift($this->state['up_mid_list'], $upMid);
        }
    }

    /**
     * 处理待处理UpMid数量
     * @return int
     */
    public function pendingUpMidCount(): int
    {
        return count($this->state['wait_up_mid_list']);
    }

    /**
     * 处理totalUpMid数量
     * @return int
     */
    public function totalUpMidCount(): int
    {
        return count($this->state['up_mid_list']);
    }

    /**
     * 处理processedUpMid数量
     * @return int
     */
    public function processedUpMidCount(): int
    {
        return max(0, $this->totalUpMidCount() - $this->pendingUpMidCount());
    }

    /**
     * 处理begin预约批量
     * @param string $upMid
     * @param int $reservationTotal
     * @return void
     */
    public function beginReservationBatch(string $upMid, int $reservationTotal): void
    {
        $this->state['current_batch_up_mid'] = trim($upMid);
        $this->state['current_batch_reservation_total'] = max(0, $reservationTotal);
        $this->state['current_batch_processed_reservation_count'] = 0;
    }

    /**
     * 处理current批量UpMid
     * @return ?string
     */
    public function currentBatchUpMid(): ?string
    {
        $upMid = trim((string)$this->state['current_batch_up_mid']);

        return $upMid !== '' ? $upMid : null;
    }

    /**
     * 处理current批量预约Total
     * @return int
     */
    public function currentBatchReservationTotal(): int
    {
        return (int)$this->state['current_batch_reservation_total'];
    }

    /**
     * 处理current批量Processed预约数量
     * @return int
     */
    public function currentBatchProcessedReservationCount(): int
    {
        return (int)$this->state['current_batch_processed_reservation_count'];
    }

    /**
     * 处理incrementCurrent批量Processed预约数量
     * @return void
     */
    public function incrementCurrentBatchProcessedReservationCount(): void
    {
        $this->state['current_batch_processed_reservation_count'] = $this->currentBatchProcessedReservationCount() + 1;
    }

    /**
     * 删除或清理预约批量
     * @return void
     */
    public function clearReservationBatch(): void
    {
        $this->state['current_batch_up_mid'] = '';
        $this->state['current_batch_reservation_total'] = 0;
        $this->state['current_batch_processed_reservation_count'] = 0;
    }

    /**
     * 处理待处理预约数量
     * @return int
     */
    public function pendingReservationCount(): int
    {
        return count($this->state['reservation_queue']);
    }

    /**
     * 发现ed预约Total
     * @return int
     */
    public function discoveredReservationTotal(): int
    {
        return (int)$this->state['discovered_reservation_total'];
    }

    /**
     * 处理processed预约数量
     * @return int
     */
    public function processedReservationCount(): int
    {
        return (int)$this->state['processed_reservation_count'];
    }

    /**
     * 处理incrementProcessed预约数量
     * @return void
     */
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

    /**
     * @param array<string, mixed> $reservation
     */
    public function requeueReservation(array $reservation): void
    {
        $sid = trim((string)($reservation['sid'] ?? ''));
        if ($sid === '') {
            return;
        }

        foreach ($this->state['reservation_queue'] as $queued) {
            if (is_array($queued) && trim((string)($queued['sid'] ?? '')) === $sid) {
                return;
            }
        }

        array_unshift($this->state['reservation_queue'], $reservation);
        if (!in_array($sid, $this->state['reservation_keys'], true)) {
            $this->state['reservation_keys'][] = $sid;
        }
    }

    /**
     * 判断Work是否满足条件
     * @return bool
     */
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
