<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use Bhp\Cache\Cache;

final class ActivityFlowStore
{
    private const CACHE_KEY_PREFIX = 'activity_flow_day:';

    public function __construct(private readonly string $scope = 'ActivityLottery')
    {
        $this->ensureCachePathReady();
        Cache::initCache($this->scope);
    }

    /**
     * @param ActivityFlow[] $flows
     */
    public function save(array $flows): void
    {
        $grouped = [];
        foreach ($flows as $flow) {
            $grouped[$flow->bizDate()][] = $flow->toArray();
        }

        foreach ($grouped as $bizDate => $items) {
            Cache::set($this->cacheKey((string)$bizDate), array_values($items), $this->scope);
        }
    }

    /**
     * @return ActivityFlow[]
     */
    public function load(string $bizDate): array
    {
        $rows = Cache::get($this->cacheKey($bizDate), $this->scope);
        if (!is_array($rows)) {
            return [];
        }

        $flows = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $flows[] = ActivityFlow::fromArray($row);
            }
        }

        return $flows;
    }

    private function cacheKey(string $bizDate): string
    {
        return self::CACHE_KEY_PREFIX . trim($bizDate);
    }

    private function ensureCachePathReady(): void
    {
        if (defined('PROFILE_CACHE_PATH')) {
            return;
        }

        $path = sys_get_temp_dir() . '/bhp-activity-lottery-cache/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        define('PROFILE_CACHE_PATH', $path);
    }
}
