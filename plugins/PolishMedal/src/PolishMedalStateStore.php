<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\PolishMedal;

use Bhp\Cache\Cache;

final class PolishMedalStateStore
{
    private const CACHE_SCOPE = 'PolishMedal';
    private const CACHE_KEY = 'state';

    /**
     * 初始化 PolishMedalStateStore
     * @param Cache $cache
     */
    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    /**
     * 处理加载
     * @return PolishMedalRuntimeState
     */
    public function load(): PolishMedalRuntimeState
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $state = $this->cache->pull(self::CACHE_KEY, self::CACHE_SCOPE);

        return PolishMedalRuntimeState::bootstrap(is_array($state) ? $state : []);
    }

    /**
     * 处理保存
     * @param PolishMedalRuntimeState $state
     * @return void
     */
    public function save(PolishMedalRuntimeState $state): void
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $this->cache->put(self::CACHE_KEY, $state->all(), self::CACHE_SCOPE);
    }
}
