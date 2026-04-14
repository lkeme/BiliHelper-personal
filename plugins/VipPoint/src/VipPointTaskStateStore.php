<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint;

use Bhp\Cache\Cache;

final class VipPointTaskStateStore
{
    private const CACHE_SCOPE = 'VipPoint';
    private const CACHE_KEY = 'tasks';

    /**
     * 初始化 VipPointTaskStateStore
     * @param Cache $cache
     */
    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function load(): array
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $tasks = $this->cache->pull(self::CACHE_KEY, self::CACHE_SCOPE);

        return is_array($tasks) ? $tasks : [];
    }

    /**
     * @param array<string, array<string, mixed>> $tasks
     */
    public function save(array $tasks): void
    {
        $this->cache->initializeScope(self::CACHE_SCOPE);
        $this->cache->put(self::CACHE_KEY, $tasks, self::CACHE_SCOPE);
    }

    /**
     * @param array<string, array<string, mixed>> $tasks
     * @param array<int|string, string> $targetTasks
     * @return array<string, mixed>
     */
    public function ensureDay(array &$tasks, string $date, array $targetTasks): array
    {
        if (!isset($tasks[$date])) {
            $tasks[$date] = [
                'start' => true,
                'DelayedAction' => null,
            ];

            foreach ($targetTasks as $target => $label) {
                $taskKey = is_string($target) ? $target : $label;
                $tasks[$date][$taskKey] = false;
            }
        }

        return $tasks[$date];
    }
}
