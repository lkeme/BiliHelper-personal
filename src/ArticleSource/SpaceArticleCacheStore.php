<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

use Bhp\Cache\Cache;

final class SpaceArticleCacheStore
{
    private const CACHE_SCOPE = 'SpaceArticleSource';
    private const CACHE_KEY = 'daily_snapshots';

    public function __construct(
        private readonly Cache $cache,
        private readonly int $retentionDays = 3,
    ) {
    }

    public function load(string $bizDate): ?SpaceArticleDailySnapshot
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $snapshots = $this->cache->pull(self::CACHE_KEY, self::CACHE_SCOPE);
        if (!is_array($snapshots) || !isset($snapshots[$bizDate]) || !is_array($snapshots[$bizDate])) {
            return null;
        }

        return SpaceArticleDailySnapshot::fromArray($snapshots[$bizDate]);
    }

    public function save(SpaceArticleDailySnapshot $snapshot): void
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $snapshots = $this->cache->pull(self::CACHE_KEY, self::CACHE_SCOPE);
        if (!is_array($snapshots)) {
            $snapshots = [];
        }

        $snapshots[$snapshot->bizDate] = $snapshot->toArray();
        $snapshots = $this->prune($snapshots, $snapshot->bizDate);

        $this->cache->put(self::CACHE_KEY, $snapshots, self::CACHE_SCOPE);
    }

    /**
     * @param array<string, array<string, mixed>> $snapshots
     * @return array<string, array<string, mixed>>
     */
    private function prune(array $snapshots, string $currentBizDate): array
    {
        $currentTimestamp = strtotime($currentBizDate . ' 00:00:00') ?: 0;
        if ($currentTimestamp <= 0) {
            return $snapshots;
        }

        $minTimestamp = $currentTimestamp - (($this->retentionDays - 1) * 86400);
        foreach ($snapshots as $bizDate => $_snapshot) {
            $timestamp = strtotime($bizDate . ' 00:00:00') ?: 0;
            if ($timestamp <= 0 || $timestamp < $minTimestamp) {
                unset($snapshots[$bizDate]);
            }
        }

        ksort($snapshots);

        return $snapshots;
    }
}
