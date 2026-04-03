<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Pool;

final class ActivityLaneLimiter
{
    /**
     * @var array<string, int>
     */
    private readonly array $laneCooldownSeconds;
    /**
     * @var array<string, int>
     */
    private array $laneNextRunAt;

    /**
     * @param array<string, int> $laneCooldownSeconds
     * @param array<string, int> $laneNextRunAt
     */
    public function __construct(array $laneCooldownSeconds = [], array $laneNextRunAt = [])
    {
        $this->laneCooldownSeconds = $this->normalizeLaneInts(
            $laneCooldownSeconds === [] ? $this->defaultCooldownSeconds() : $laneCooldownSeconds
        );
        $this->laneNextRunAt = $this->normalizeLaneInts($laneNextRunAt);
    }

    public function canPass(string $lane, int $now): bool
    {
        return $this->blockedUntil($lane) <= $now;
    }

    public function reserve(string $lane, int $now): void
    {
        $cooldown = $this->cooldownSeconds($lane);
        if ($cooldown <= 0) {
            $this->laneNextRunAt[$lane] = $now;
            return;
        }

        $this->laneNextRunAt[$lane] = $now + $cooldown;
    }

    public function cooldownSeconds(string $lane): int
    {
        return (int)($this->laneCooldownSeconds[$lane] ?? 0);
    }

    public function blockedUntil(string $lane): int
    {
        return (int)($this->laneNextRunAt[$lane] ?? 0);
    }

    /**
     * @return array<string, int>
     */
    public function state(): array
    {
        return $this->laneNextRunAt;
    }

    /**
     * @return array<string, int>
     */
    private function defaultCooldownSeconds(): array
    {
        return [
            'page_fetch' => 2,
            'task_status' => 30,
            'follow' => 15,
            'unfollow' => 15,
            'draw_refresh' => 8,
            'draw_execute' => 10,
            'claim_reward' => 15,
        ];
    }

    /**
     * @param array<string, int> $values
     * @return array<string, int>
     */
    private function normalizeLaneInts(array $values): array
    {
        $normalized = [];
        foreach ($values as $lane => $value) {
            $name = trim((string)$lane);
            if ($name === '') {
                continue;
            }
            $normalized[$name] = max(0, (int)$value);
        }

        return $normalized;
    }
}

