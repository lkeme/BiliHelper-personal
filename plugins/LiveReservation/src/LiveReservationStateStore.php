<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LiveReservation;

use Bhp\Cache\Cache;

final class LiveReservationStateStore
{
    private const CACHE_SCOPE = 'LiveReservation';

    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'biz_date' => '',
            'source_synced' => false,
            'source_cv_id' => 0,
            'up_mid_list' => [],
            'wait_up_mid_list' => [],
            'reservation_queue' => [],
            'reservation_keys' => [],
            'discovered_reservation_total' => 0,
            'processed_reservation_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $stored = $this->cache->pull('config', self::CACHE_SCOPE);
        if (!is_array($stored)) {
            return self::defaults();
        }

        return array_replace(self::defaults(), $stored);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function save(array $state): void
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $this->cache->put('config', $state, self::CACHE_SCOPE);
    }
}
