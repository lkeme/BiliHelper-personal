<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\MainSite;

use Bhp\Cache\Cache;

final class MainSiteRecordStore
{
    private const CACHE_SCOPE = 'MainSite';

    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $records = $this->cache->pull('records', self::CACHE_SCOPE);

        return array_merge($this->defaults(), is_array($records) ? $records : []);
    }

    /**
     * @param array<string, mixed> $records
     */
    public function save(array $records): void
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $this->cache->put('records', $records, self::CACHE_SCOPE);
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
