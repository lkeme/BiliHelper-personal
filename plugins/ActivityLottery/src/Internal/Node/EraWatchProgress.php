<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot;

final class EraWatchProgress
{
    /**
     * @param array<string, mixed>|null $progress
     * @return int[]
     */
    public static function thresholds(EraTaskSnapshot $task, ?array $progress = null): array
    {
        $thresholds = [];
        $checkpointGroups = [];
        if (is_array($progress)) {
            foreach (['accumulative_check_points', 'check_points'] as $key) {
                if (is_array($progress[$key] ?? null)) {
                    $checkpointGroups[] = $progress[$key];
                }
            }
        }
        if ($checkpointGroups === []) {
            $checkpointGroups[] = $task->checkpoints();
        }

        foreach ($checkpointGroups as $checkpoints) {
            foreach ($checkpoints as $checkpoint) {
                if (!is_array($checkpoint)) {
                    continue;
                }

                foreach (($checkpoint['list'] ?? []) as $indicator) {
                    if (!is_array($indicator)) {
                        continue;
                    }

                    $limit = (int)($indicator['limit'] ?? $indicator['target_val'] ?? 0);
                    if ($limit > 0) {
                        $thresholds[] = $limit;
                    }
                }
            }
        }

        if ($thresholds === [] && $task->requiredWatchSeconds() > 0) {
            $thresholds[] = $task->requiredWatchSeconds();
        }

        $thresholds = array_values(array_unique(array_filter($thresholds, static fn (int $limit): bool => $limit > 0)));
        sort($thresholds);

        return $thresholds;
    }

    /**
     * @param array<string, mixed>|null $progress
     */
    public static function currentSeconds(EraTaskSnapshot $task, ?array $progress = null): int
    {
        $current = 0;

        if (is_array($progress)) {
            foreach (['accumulative_check_points', 'check_points'] as $key) {
                foreach (($progress[$key] ?? []) as $checkpoint) {
                    if (!is_array($checkpoint)) {
                        continue;
                    }

                    foreach (($checkpoint['list'] ?? []) as $indicator) {
                        if (!is_array($indicator)) {
                            continue;
                        }

                        $current = max($current, (int)($indicator['cur_value'] ?? $indicator['current_val'] ?? 0));
                    }
                }
            }

            foreach (($progress['indicators'] ?? []) as $indicator) {
                if (!is_array($indicator)) {
                    continue;
                }

                $current = max($current, (int)($indicator['cur_value'] ?? $indicator['current_val'] ?? 0));
            }
        }

        foreach ($task->checkpoints() as $checkpoint) {
            if (!is_array($checkpoint)) {
                continue;
            }

            foreach (($checkpoint['list'] ?? []) as $indicator) {
                if (!is_array($indicator)) {
                    continue;
                }

                $current = max($current, (int)($indicator['cur_value'] ?? $indicator['current_val'] ?? 0));
            }
        }

        return max(0, $current);
    }

    /**
     * @param array<string, mixed>|null $progress
     */
    public static function targetSeconds(EraTaskSnapshot $task, ?array $progress = null, ?int $current = null): int
    {
        $thresholds = self::thresholds($task, $progress);
        if ($thresholds === []) {
            return 0;
        }

        $current ??= self::currentSeconds($task, $progress);
        $limit = max($thresholds);
        $target = $limit + self::bufferSeconds($limit);

        return $current >= $target ? 0 : $target;
    }

    /**
     * @param array<string, mixed>|null $progress
     */
    public static function resolveWaitSeconds(EraTaskSnapshot $task, int $duration, ?array $progress = null, ?int $current = null): int
    {
        $playableDuration = max(15, max(1, $duration) - 1);
        $target = self::targetSeconds($task, $progress, $current);
        if ($target > 0) {
            return max(15, min($playableDuration, $target));
        }

        return min($playableDuration, 15);
    }

    private static function bufferSeconds(int $thresholdSeconds): int
    {
        if ($thresholdSeconds >= 3600) {
            return 300;
        }
        if ($thresholdSeconds >= 1800) {
            return 180;
        }
        if ($thresholdSeconds >= 600) {
            return 120;
        }
        if ($thresholdSeconds >= 180) {
            return 60;
        }

        return 30;
    }
}

