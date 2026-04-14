<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\PolishMedal;

final class PolishMedalRoundPlanner
{
    /**
     * 初始化 PolishMedalRoundPlanner
     * @param int $maxLightQueue
     */
    public function __construct(
        private readonly int $maxLightQueue = 30,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $medals
     * @return array{
     *     delete_queue: array<int, array<string, mixed>>,
     *     light_queue: array<int, array<string, mixed>>,
     *     stats: array<string, int>
     * }
     */
    public function plan(array $medals, bool $cleanupInvalidMedal): array
    {
        $deleteQueue = [];
        $lightQueue = [];
        $loggedOffCount = 0;
        $liveUnlitCount = 0;

        foreach ($medals as $medal) {
            if (!is_array($medal)) {
                continue;
            }

            if ($this->isLoggedOff($medal)) {
                $loggedOffCount++;
                if ($cleanupInvalidMedal && (int)($medal['medal_id'] ?? 0) > 0 && (bool)($medal['can_delete'] ?? true)) {
                    $deleteQueue[] = $medal;
                }
                continue;
            }

            if ($this->isLiveAndUnlit($medal)) {
                $liveUnlitCount++;
                if (count($lightQueue) < $this->maxLightQueue) {
                    $lightQueue[] = $medal;
                }
            }
        }

        return [
            'delete_queue' => $deleteQueue,
            'light_queue' => $lightQueue,
            'stats' => [
                'total_medal_count' => count($medals),
                'logged_off_count' => $loggedOffCount,
                'live_unlit_count' => $liveUnlitCount,
                'queued_light_count' => count($lightQueue),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $medal
     */
    private function isLoggedOff(array $medal): bool
    {
        return trim((string)($medal['anchor_name'] ?? '')) === '账号已注销';
    }

    /**
     * @param array<string, mixed> $medal
     */
    private function isLiveAndUnlit(array $medal): bool
    {
        return (int)($medal['living_status'] ?? 0) === 1
            && (int)($medal['is_lighted'] ?? 0) === 0
            && (int)($medal['room_id'] ?? 0) > 0
            && (int)($medal['target_id'] ?? 0) > 0;
    }
}
