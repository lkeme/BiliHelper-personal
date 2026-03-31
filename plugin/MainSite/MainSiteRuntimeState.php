<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\MainSite;

final class MainSiteRuntimeState
{
    private const MARKER_BUCKETS = ['watch', 'share', 'coin'];

    /**
     * @param array<string, mixed> $records
     * @param array<string, mixed> $defaults
     */
    public static function bootstrap(array $records, array $defaults): self
    {
        return (new self(array_merge($defaults, $records)))->normalize();
    }

    /**
     * @param array<string, mixed> $records
     */
    public function __construct(private array $records)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->records;
    }

    private function normalize(): self
    {
        foreach (self::MARKER_BUCKETS as $bucket) {
            $this->normalizeMarkerBucket($bucket);
        }

        $this->normalizePendingWatch();
        $this->normalizePendingCoins();

        return $this;
    }

    private function normalizeMarkerBucket(string $bucket): void
    {
        $markers = $this->records[$bucket] ?? [];
        if (!is_array($markers)) {
            $markers = [];
        }

        $this->records[$bucket] = array_values(array_unique(array_filter(
            $markers,
            static fn (mixed $marker): bool => is_string($marker)
        )));
    }

    public function hasMarker(string $bucket, string $key): bool
    {
        $this->normalizeMarkerBucket($bucket);

        return in_array($key, $this->records[$bucket], true);
    }

    public function markCompleted(string $bucket, string $key): void
    {
        $this->normalizeMarkerBucket($bucket);

        if (!in_array($key, $this->records[$bucket], true)) {
            $this->records[$bucket][] = $key;
        }
    }

    /**
     * @return array<string, int|string>|null
     */
    public function pendingWatch(): ?array
    {
        $this->normalizePendingWatch();

        return $this->records['watch_pending'];
    }

    /**
     * @param array<string, int|string> $pendingWatch
     */
    public function setPendingWatch(array $pendingWatch): void
    {
        $this->records['watch_pending'] = $pendingWatch;
        $this->normalizePendingWatch();
    }

    public function clearPendingWatch(): void
    {
        $this->records['watch_pending'] = null;
    }

    /**
     * @return string[]
     */
    public function pendingCoins(): array
    {
        $this->normalizePendingCoins();

        return $this->records['coin_pending'];
    }

    /**
     * @param string[] $pendingCoins
     */
    public function setPendingCoins(array $pendingCoins): void
    {
        $this->records['coin_pending'] = array_values($pendingCoins);
        $this->normalizePendingCoins();
    }

    public function clearPendingCoins(): void
    {
        $this->records['coin_pending'] = [];
    }

    private function normalizePendingWatch(): void
    {
        $pending = $this->records['watch_pending'] ?? null;
        if (!is_array($pending)) {
            $this->records['watch_pending'] = null;
            return;
        }

        $normalized = [];
        foreach ($pending as $key => $value) {
            if (is_string($value) || is_int($value)) {
                $normalized[(string)$key] = $value;
            }
        }

        $this->records['watch_pending'] = $normalized === [] ? null : $normalized;
    }

    private function normalizePendingCoins(): void
    {
        $pending = $this->records['coin_pending'] ?? [];
        if (!is_array($pending)) {
            $pending = [];
        }

        $this->records['coin_pending'] = array_values(array_unique(array_filter(
            $pending,
            static fn (mixed $value): bool => is_string($value)
        )));
    }
}
