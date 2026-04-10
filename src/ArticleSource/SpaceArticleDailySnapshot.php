<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

final class SpaceArticleDailySnapshot
{
    /**
     * @param string[] $liveReservationUpMids
     * @param string[] $lotteryDynamicIds
     */
    public function __construct(
        public readonly string $bizDate,
        public readonly bool $fetchAttempted,
        public readonly int $fetchedAt,
        public readonly ?int $reservationSourceCvId,
        public readonly ?string $reservationSourceTitle,
        public readonly ?int $lotterySourceCvId,
        public readonly ?string $lotterySourceTitle,
        public readonly array $liveReservationUpMids,
        public readonly array $lotteryDynamicIds,
    ) {
    }

    public static function empty(string $bizDate, int $fetchedAt): self
    {
        return new self(
            $bizDate,
            true,
            $fetchedAt,
            null,
            null,
            null,
            null,
            [],
            [],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['biz_date'] ?? ''),
            (bool)($data['fetch_attempted'] ?? false),
            (int)($data['fetched_at'] ?? 0),
            isset($data['reservation_source_cv_id']) && is_numeric($data['reservation_source_cv_id']) ? (int)$data['reservation_source_cv_id'] : null,
            self::normalizeNullableString($data['reservation_source_title'] ?? null),
            isset($data['lottery_source_cv_id']) && is_numeric($data['lottery_source_cv_id']) ? (int)$data['lottery_source_cv_id'] : null,
            self::normalizeNullableString($data['lottery_source_title'] ?? null),
            self::normalizeList($data['live_reservation_up_mids'] ?? []),
            self::normalizeList($data['lottery_dynamic_ids'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'biz_date' => $this->bizDate,
            'fetch_attempted' => $this->fetchAttempted,
            'fetched_at' => $this->fetchedAt,
            'reservation_source_cv_id' => $this->reservationSourceCvId,
            'reservation_source_title' => $this->reservationSourceTitle,
            'lottery_source_cv_id' => $this->lotterySourceCvId,
            'lottery_source_title' => $this->lotterySourceTitle,
            'live_reservation_up_mids' => array_values($this->liveReservationUpMids),
            'lottery_dynamic_ids' => array_values($this->lotteryDynamicIds),
        ];
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private static function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = array_values(array_filter(array_map(
            static fn(mixed $item): string => trim((string)$item),
            $value,
        ), static fn(string $item): bool => $item !== ''));

        return array_values(array_unique($items));
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string)$value);

        return $normalized !== '' ? $normalized : null;
    }
}
