<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\DynamicLottery;

use Bhp\Cache\Cache;

final class DynamicLotteryStateStore
{
    private const CACHE_SCOPE = 'DynamicLottery';

    /**
     * 初始化 DynamicLotteryStateStore
     * @param Cache $cache
     */
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
            'dynamic_list' => [],
            'wait_dynamic_list' => [],
            'lottery_list' => [],
            'wait_lottery_list' => [],
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
