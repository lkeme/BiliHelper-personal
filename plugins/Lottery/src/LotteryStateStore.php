<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\Cache\Cache;

final class LotteryStateStore
{
    private const CACHE_SCOPE = 'Lottery';

    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    /**
     * @return array<string, array<int|string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'cv_list' => [],
            'wait_cv_list' => [],
            'dynamic_list' => [],
            'wait_dynamic_list' => [],
            'lottery_list' => [],
            'wait_lottery_list' => [],
        ];
    }

    /**
     * @return array<string, array<int|string, mixed>>
     */
    public function load(): array
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $stored = $this->cache->pull('config', self::CACHE_SCOPE);
        if (!is_array($stored)) {
            return self::defaults();
        }

        return array_merge(self::defaults(), $stored);
    }

    /**
     * @param array<string, array<int|string, mixed>> $state
     */
    public function save(array $state): void
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $this->cache->put('config', $state, self::CACHE_SCOPE);
    }

    /**
     * @param array<string, array<int|string, mixed>> $state
     */
    public function addCv(array &$state, int $cv): void
    {
        if (!in_array($cv, $state['cv_list'], true)) {
            $state['cv_list'][] = $cv;
            $state['wait_cv_list'][] = $cv;
        }
    }

    /**
     * @param array<string, array<int|string, mixed>> $state
     */
    public function addDynamic(array &$state, int $dynamic): void
    {
        if (!in_array($dynamic, $state['dynamic_list'], true)) {
            $state['dynamic_list'][] = $dynamic;
            $state['wait_dynamic_list'][] = $dynamic;
        }
    }

    /**
     * @param array<string, array<int|string, mixed>> $state
     * @param array<string, mixed> $lottery
     */
    public function addLottery(array &$state, array $lottery): void
    {
        $key = "rid{$lottery['rid']}";
        if (!array_key_exists($key, $state['lottery_list'])) {
            $state['lottery_list'][$key] = $lottery;
            $state['wait_lottery_list'][$key] = $lottery;
        }
    }
}
