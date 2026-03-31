<?php declare(strict_types=1);

namespace Bhp\Scheduler;

use Bhp\Cache\Cache;

final class SchedulerStateStore
{
    private const CACHE_SCOPE = 'Scheduler';
    private const CACHE_KEY = 'tasks';

    /**
     * @return array<string, array{next_run_at: float, failure_count: int, circuit_open_until: float}>
     */
    public function load(): array
    {
        Cache::initCache(self::CACHE_SCOPE);
        $states = Cache::get(self::CACHE_KEY, self::CACHE_SCOPE);

        return $this->normalizeStates($states);
    }

    /**
     * @param array<string, array{next_run_at: float, failure_count: int, circuit_open_until: float}> $states
     */
    public function save(array $states): void
    {
        Cache::initCache(self::CACHE_SCOPE);
        Cache::set(self::CACHE_KEY, $states, self::CACHE_SCOPE);
    }

    public function saveTaskState(
        string $hook,
        float $nextRunAtEpoch,
        int $failureCount,
        float $circuitOpenUntilEpoch,
    ): void {
        $states = $this->load();
        $states[$hook] = [
            'next_run_at' => $nextRunAtEpoch,
            'failure_count' => max(0, $failureCount),
            'circuit_open_until' => max(0.0, $circuitOpenUntilEpoch),
        ];

        $this->save($states);
    }

    /**
     * @return array<string, array{next_run_at: float, failure_count: int, circuit_open_until: float}>
     */
    private function normalizeStates(mixed $states): array
    {
        if (!is_array($states)) {
            return [];
        }

        $normalized = [];
        foreach ($states as $hook => $state) {
            if (!is_string($hook) || !is_array($state)) {
                continue;
            }

            $normalized[$hook] = [
                'next_run_at' => isset($state['next_run_at']) && is_numeric($state['next_run_at']) ? (float)$state['next_run_at'] : 0.0,
                'failure_count' => isset($state['failure_count']) && is_numeric($state['failure_count']) ? max(0, (int)$state['failure_count']) : 0,
                'circuit_open_until' => isset($state['circuit_open_until']) && is_numeric($state['circuit_open_until']) ? max(0.0, (float)$state['circuit_open_until']) : 0.0,
            ];
        }

        return $normalized;
    }
}
