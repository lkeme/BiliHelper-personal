<?php declare(strict_types=1);

namespace Bhp\Scheduler;

final class TaskResult
{
    private function __construct(
        public readonly bool $success = true,
        public readonly ?float $nextRunAfterSeconds = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function keepSchedule(?string $message = null): self
    {
        return new self(true, null, $message);
    }

    public static function after(float $seconds, ?string $message = null): self
    {
        return new self(true, max(0.0, $seconds), $message);
    }

    public static function retryAfter(float $seconds, ?string $message = null): self
    {
        return new self(false, max(0.0, $seconds), $message);
    }

    public static function nextAt(
        int $hour,
        int $minute = 0,
        int $second = 0,
        int $randomMinMinutes = 0,
        int $randomMaxMinutes = 0,
        ?string $message = null,
    ): self {
        return new self(true, self::secondsUntilNextAt($hour, $minute, $second, $randomMinMinutes, $randomMaxMinutes), $message);
    }

    public static function nextDayAt(
        int $hour,
        int $minute = 0,
        int $second = 0,
        int $randomMinMinutes = 0,
        int $randomMaxMinutes = 0,
        ?string $message = null,
    ): self {
        return new self(true, self::secondsUntilNextDayAt($hour, $minute, $second, $randomMinMinutes, $randomMaxMinutes), $message);
    }

    public static function secondsUntilNextAt(
        int $hour,
        int $minute = 0,
        int $second = 0,
        int $randomMinMinutes = 0,
        int $randomMaxMinutes = 0,
    ): float {
        $today = strtotime('today');
        $target = $today + ($hour * 3600) + ($minute * 60) + $second;
        $now = time();

        if ($target <= $now) {
            $target = strtotime('tomorrow') + ($hour * 3600) + ($minute * 60) + $second;
        }

        if ($randomMaxMinutes > 0 && $randomMaxMinutes >= $randomMinMinutes) {
            $target += mt_rand($randomMinMinutes, $randomMaxMinutes) * 60;
        }

        return max(0.0, (float)($target - $now));
    }

    public static function secondsUntilNextDayAt(
        int $hour,
        int $minute = 0,
        int $second = 0,
        int $randomMinMinutes = 0,
        int $randomMaxMinutes = 0,
        ?int $nowTimestamp = null,
    ): float {
        $now = $nowTimestamp ?? time();
        $target = strtotime('tomorrow', $now) + ($hour * 3600) + ($minute * 60) + $second;

        if ($randomMaxMinutes > 0 && $randomMaxMinutes >= $randomMinMinutes) {
            $target += mt_rand($randomMinMinutes, $randomMaxMinutes) * 60;
        }

        return max(0.0, (float)($target - $now));
    }
}
