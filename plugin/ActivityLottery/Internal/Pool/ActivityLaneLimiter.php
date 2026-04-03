<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Pool;

use RuntimeException;

final class ActivityLaneLimiter
{
    /**
     * @var array<string, int>
     */
    private readonly array $laneCooldownSeconds;
    /**
     * @var array<string, true>
     */
    private readonly array $knownLanes;
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
        $defaultCooldowns = $this->normalizeLaneInts($this->defaultCooldownSeconds());
        $customCooldowns = $this->normalizeLaneInts($laneCooldownSeconds);
        $this->laneCooldownSeconds = array_replace($defaultCooldowns, $customCooldowns);
        $this->knownLanes = array_fill_keys(array_keys($this->laneCooldownSeconds), true);

        $this->laneNextRunAt = [];
        foreach ($this->normalizeLaneInts($laneNextRunAt) as $lane => $nextRunAt) {
            $this->assertKnownLane($lane);
            $this->laneNextRunAt[$lane] = $nextRunAt;
        }
    }

    public function canPass(string $lane, int $now): bool
    {
        $this->assertKnownLane($lane);
        return $this->blockedUntil($lane) <= $now;
    }

    public function reserve(string $lane, int $now): void
    {
        $this->assertKnownLane($lane);
        $cooldown = $this->cooldownSeconds($lane);
        if ($cooldown <= 0) {
            $this->laneNextRunAt[$lane] = $now;
            return;
        }

        $this->laneNextRunAt[$lane] = $now + $cooldown;
    }

    public function cooldownSeconds(string $lane): int
    {
        $this->assertKnownLane($lane);
        return (int)($this->laneCooldownSeconds[$lane] ?? 0);
    }

    public function blockedUntil(string $lane): int
    {
        $this->assertKnownLane($lane);
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

    private function assertKnownLane(string $lane): void
    {
        $name = trim($lane);
        if ($name === '' || !isset($this->knownLanes[$name])) {
            throw new RuntimeException(sprintf('未知 lane: %s', $lane));
        }
    }
}
