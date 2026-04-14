<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityInfoUpdate\Internal;

final class EraActivityPage
{
    /**
     * @param string[] $topicIds
     * @param string[] $followUids
     * @param EraActivityFollowTarget[] $followTargets
     * @param string[] $videoIds
     * @param string[] $liveRoomIds
     * @param EraActivityTask[] $tasks
     */
    public function __construct(
        public readonly string $title,
        public readonly string $pageId,
        public readonly string $activityId,
        public readonly string $lotteryId,
        public readonly int $startTime,
        public readonly int $endTime,
        public readonly array $topicIds,
        public readonly array $followUids,
        public readonly array $followTargets,
        public readonly array $videoIds,
        public readonly array $liveRoomIds,
        public readonly array $tasks,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $tasks = [];
        foreach (($data['tasks'] ?? []) as $task) {
            if (!is_array($task)) {
                continue;
            }

            $tasks[] = EraActivityTask::fromArray($task);
        }

        $followTargets = [];
        foreach (($data['follow_targets'] ?? []) as $target) {
            if (!is_array($target)) {
                continue;
            }

            $followTargets[] = EraActivityFollowTarget::fromArray($target);
        }

        return new self(
            (string)($data['title'] ?? ''),
            (string)($data['page_id'] ?? ''),
            (string)($data['activity_id'] ?? ''),
            (string)($data['lottery_id'] ?? ''),
            (int)($data['start_time'] ?? 0),
            (int)($data['end_time'] ?? 0),
            self::normalizeStringArray($data['topic_ids'] ?? []),
            self::normalizeStringArray($data['follow_uids'] ?? []),
            $followTargets,
            self::normalizeStringArray($data['video_ids'] ?? []),
            self::normalizeStringArray($data['live_room_ids'] ?? []),
            $tasks,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'page_id' => $this->pageId,
            'activity_id' => $this->activityId,
            'lottery_id' => $this->lotteryId,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'topic_ids' => $this->topicIds,
            'follow_uids' => $this->followUids,
            'follow_targets' => array_map(static fn(EraActivityFollowTarget $target): array => $target->toArray(), $this->followTargets),
            'video_ids' => $this->videoIds,
            'live_room_ids' => $this->liveRoomIds,
            'tasks' => array_map(static fn(EraActivityTask $task): array => $task->toArray(), $this->tasks),
        ];
    }

    /**
     * 处理with时间Range
     * @param int $startTime
     * @param int $endTime
     * @return self
     */
    public function withTimeRange(int $startTime, int $endTime): self
    {
        return new self(
            $this->title,
            $this->pageId,
            $this->activityId,
            $this->lotteryId,
            $startTime,
            $endTime,
            $this->topicIds,
            $this->followUids,
            $this->followTargets,
            $this->videoIds,
            $this->liveRoomIds,
            $this->tasks,
        );
    }

    /**
     * 判断Expired是否满足条件
     * @param int $now
     * @return bool
     */
    public function isExpired(?int $now = null): bool
    {
        $now ??= time();

        return $this->endTime > 0 && $this->endTime <= $now;
    }

    /**
     * 判断NotStarted是否满足条件
     * @param int $now
     * @return bool
     */
    public function isNotStarted(?int $now = null): bool
    {
        $now ??= time();

        return $this->startTime > 0 && $this->startTime > $now;
    }

    /**
     * @return array<string, int>
     */
    public function taskCapabilitySummary(): array
    {
        $summary = [];
        foreach ($this->tasks as $task) {
            $summary[$task->capability] = ($summary[$task->capability] ?? 0) + 1;
        }

        ksort($summary);

        return $summary;
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private static function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_string($item) || is_int($item)) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $normalized[] = $item;
                }
            }
        }

        return array_values(array_unique($normalized));
    }
}
