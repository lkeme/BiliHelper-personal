<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Pool;

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

    /**
     * 判断Pass是否满足条件
     * @param string $lane
     * @param int $now
     * @return bool
     */
    public function canPass(string $lane, int $now): bool
    {
        $this->assertKnownLane($lane);
        return $this->blockedUntil($lane) <= $now;
    }

    /**
     * 处理预留
     * @param string $lane
     * @param int $now
     * @return void
     */
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

    /**
     * 处理cooldownSeconds
     * @param string $lane
     * @return int
     */
    public function cooldownSeconds(string $lane): int
    {
        $this->assertKnownLane($lane);
        return (int)($this->laneCooldownSeconds[$lane] ?? 0);
    }

    /**
     * 处理blockedUntil
     * @param string $lane
     * @return int
     */
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
            'draw_validate' => 0,
            'draw_refresh' => 0,
            'draw_execute' => 0,
            'draw_record' => 0,
            'draw_notify' => 0,
            'draw_finalize' => 0,
            'claim_reward' => 15,
            'watch_video' => 30,
            'watch_live_init' => 15,
            'watch_live' => 60,
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

    /**
     * 断言KnownLane
     * @param string $lane
     * @return void
     */
    private function assertKnownLane(string $lane): void
    {
        $name = trim($lane);
        if ($name === '' || !isset($this->knownLanes[$name])) {
            throw new RuntimeException(sprintf('未知 lane: %s', $lane));
        }
    }
}

