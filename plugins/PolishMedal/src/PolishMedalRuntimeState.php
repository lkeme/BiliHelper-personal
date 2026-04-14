<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\PolishMedal;

final class PolishMedalRuntimeState
{
    /**
     * @param array<string, mixed> $state
     */
    public static function bootstrap(array $state): self
    {
        return new self($state);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function __construct(private array $state)
    {
        $this->state = array_replace(self::defaults(), $state);
        $this->state['round_delete_queue'] = $this->normalizeQueue($this->state['round_delete_queue'] ?? []);
        $this->state['round_light_queue'] = $this->normalizeQueue($this->state['round_light_queue'] ?? []);
        $this->state['round_stats'] = $this->normalizeStats($this->state['round_stats'] ?? []);
        $this->state['round_refreshed_at'] = max(0, (int)($this->state['round_refreshed_at'] ?? 0));
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'round_refreshed_at' => 0,
            'round_delete_queue' => [],
            'round_light_queue' => [],
            'round_stats' => [
                'total_medal_count' => 0,
                'logged_off_count' => 0,
                'live_unlit_count' => 0,
                'queued_light_count' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->state;
    }

    /**
     * 处理roundRefreshedAt
     * @return int
     */
    public function roundRefreshedAt(): int
    {
        return (int)$this->state['round_refreshed_at'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roundDeleteQueue(): array
    {
        /** @var array<int, array<string, mixed>> $queue */
        $queue = $this->state['round_delete_queue'];
        return $queue;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roundLightQueue(): array
    {
        /** @var array<int, array<string, mixed>> $queue */
        $queue = $this->state['round_light_queue'];
        return $queue;
    }

    /**
     * @return array<string, int>
     */
    public function roundStats(): array
    {
        /** @var array<string, int> $stats */
        $stats = $this->state['round_stats'];
        return $stats;
    }

    /**
     * 判断DeleteQueue是否满足条件
     * @return bool
     */
    public function hasDeleteQueue(): bool
    {
        return $this->roundDeleteQueue() !== [];
    }

    /**
     * 判断LightQueue是否满足条件
     * @return bool
     */
    public function hasLightQueue(): bool
    {
        return $this->roundLightQueue() !== [];
    }

    /**
     * 设置Round
     * @param int $refreshedAt
     * @param array $deleteQueue
     * @param array $lightQueue
     * @param array $stats
     * @return void
     */
    public function setRound(int $refreshedAt, array $deleteQueue, array $lightQueue, array $stats): void
    {
        $this->state['round_refreshed_at'] = max(0, $refreshedAt);
        $this->state['round_delete_queue'] = $this->normalizeQueue($deleteQueue);
        $this->state['round_light_queue'] = $this->normalizeQueue($lightQueue);
        $this->state['round_stats'] = $this->normalizeStats($stats);
        $this->state['round_stats']['queued_light_count'] = count($this->state['round_light_queue']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function popDeleteQueue(): ?array
    {
        $queue = $this->roundDeleteQueue();
        $item = array_shift($queue);
        $this->state['round_delete_queue'] = $queue;

        return is_array($item) ? $item : null;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function requeueDeleteQueue(array $item): void
    {
        $medalId = (int)($item['medal_id'] ?? 0);
        foreach ($this->roundDeleteQueue() as $queued) {
            if ((int)($queued['medal_id'] ?? 0) === $medalId && $medalId > 0) {
                return;
            }
        }

        $queue = $this->roundDeleteQueue();
        $queue[] = $item;
        $this->state['round_delete_queue'] = $queue;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function popLightQueue(): ?array
    {
        $queue = $this->roundLightQueue();
        $item = array_shift($queue);
        $this->state['round_light_queue'] = $queue;
        $this->state['round_stats']['queued_light_count'] = count($queue);

        return is_array($item) ? $item : null;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function requeueLightQueue(array $item): void
    {
        $medalId = (int)($item['medal_id'] ?? 0);
        foreach ($this->roundLightQueue() as $queued) {
            if ((int)($queued['medal_id'] ?? 0) === $medalId && $medalId > 0) {
                return;
            }
        }

        $queue = $this->roundLightQueue();
        $queue[] = $item;
        $this->state['round_light_queue'] = $queue;
        $this->state['round_stats']['queued_light_count'] = count($queue);
    }

    /**
     * 删除或清理Round
     * @return void
     */
    public function clearRound(): void
    {
        $this->state = self::defaults();
    }

    /**
     * @param mixed $queue
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQueue(mixed $queue): array
    {
        if (!is_array($queue)) {
            return [];
        }

        $normalized = [];
        foreach ($queue as $item) {
            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param mixed $stats
     * @return array<string, int>
     */
    private function normalizeStats(mixed $stats): array
    {
        $defaults = self::defaults()['round_stats'];
        if (!is_array($stats)) {
            return $defaults;
        }

        return [
            'total_medal_count' => max(0, (int)($stats['total_medal_count'] ?? 0)),
            'logged_off_count' => max(0, (int)($stats['logged_off_count'] ?? 0)),
            'live_unlit_count' => max(0, (int)($stats['live_unlit_count'] ?? 0)),
            'queued_light_count' => max(0, (int)($stats['queued_light_count'] ?? 0)),
        ];
    }
}
