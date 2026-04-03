<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Runtime;

final class ActivityLotteryWindow
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
        $seconds = (int)date('H', $timestamp) * 3600 + (int)date('i', $timestamp) * 60 + (int)date('s', $timestamp);

        if ($this->startSeconds <= $this->endSeconds) {
            return $seconds >= $this->startSeconds && $seconds < $this->endSeconds;
        }

        return $seconds >= $this->startSeconds || $seconds < $this->endSeconds;
    }

    private static function toSeconds(string $time): int
    {
        $chunks = explode(':', trim($time));
        $hour = (int)($chunks[0] ?? 0);
        $minute = (int)($chunks[1] ?? 0);
        $second = (int)($chunks[2] ?? 0);

        return $hour * 3600 + $minute * 60 + $second;
    }
}
