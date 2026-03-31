<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\MainSite;

use Bhp\Cache\Cache;

final class MainSiteRecordStore
{
    private const CACHE_SCOPE = 'MainSite';

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        Cache::initCache(self::CACHE_SCOPE);
        $records = Cache::get('records', self::CACHE_SCOPE);

        return array_merge($this->defaults(), is_array($records) ? $records : []);
    }

    /**
     * @param array<string, mixed> $records
     */
    public function save(array $records): void
    {
        Cache::initCache(self::CACHE_SCOPE);
        Cache::set('records', $records, self::CACHE_SCOPE);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'watch' => [],
            'share' => [],
            'coin' => [],
            'watch_pending' => null,
            'coin_pending' => [],
        ];
    }
}
