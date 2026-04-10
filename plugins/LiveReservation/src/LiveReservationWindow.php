<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LiveReservation;

final class LiveReservationWindow
{
    private int $startSeconds;
    private int $endSeconds;

    public function __construct(
        private readonly string $startAt,
        private readonly string $endAt,
    ) {
        $this->startSeconds = self::toSeconds($this->startAt);
        $this->endSeconds = self::toSeconds($this->endAt);
    }

    public function contains(int $timestamp): bool
    {
        $seconds = ((int)date('H', $timestamp) * 3600) + ((int)date('i', $timestamp) * 60) + (int)date('s', $timestamp);
        if ($this->startSeconds <= $this->endSeconds) {
            return $seconds >= $this->startSeconds && $seconds < $this->endSeconds;
        }

        return $seconds >= $this->startSeconds || $seconds < $this->endSeconds;
    }

    public function secondsUntilNextStart(int $timestamp): float
    {
        [$hour, $minute, $second] = self::parseTime($this->startAt);
        $todayTarget = strtotime(date('Y-m-d', $timestamp) . sprintf(' %02d:%02d:%02d', $hour, $minute, $second));
        if ($todayTarget !== false && $todayTarget > $timestamp) {
            return max(1.0, (float)($todayTarget - $timestamp));
        }

        $tomorrowTarget = strtotime(date('Y-m-d', strtotime('+1 day', $timestamp)) . sprintf(' %02d:%02d:%02d', $hour, $minute, $second));

        return max(1.0, (float)(($tomorrowTarget ?: $timestamp) - $timestamp));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function parseTime(string $time): array
    {
        $chunks = explode(':', trim($time));

        return [
            (int)($chunks[0] ?? 0),
            (int)($chunks[1] ?? 0),
            (int)($chunks[2] ?? 0),
        ];
    }

    private static function toSeconds(string $time): int
    {
        [$hour, $minute, $second] = self::parseTime($time);

        return ($hour * 3600) + ($minute * 60) + $second;
    }
}
