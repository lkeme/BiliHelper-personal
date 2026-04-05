<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityInfoUpdate\Internal;

final class EraActivityTask
{
    public const CAPABILITY_SHARE = 'share';
    public const CAPABILITY_FOLLOW = 'follow';
    public const CAPABILITY_WATCH_VIDEO_FIXED = 'watch_video_fixed';
    public const CAPABILITY_WATCH_VIDEO_TOPIC = 'watch_video_topic';
    public const CAPABILITY_WATCH_LIVE = 'watch_live';
    public const CAPABILITY_COIN_TOPIC = 'coin_topic';
    public const CAPABILITY_LIKE_TOPIC = 'like_topic';
    public const CAPABILITY_COMMENT_TOPIC = 'comment_topic';
    public const CAPABILITY_MANUAL = 'manual_only';
    public const CAPABILITY_UNKNOWN = 'unknown';

    public const SUPPORT_NOW = 'now';
    public const SUPPORT_LATER = 'later';
    public const SUPPORT_MANUAL = 'manual';

    /**
     * @param string[] $btnBehavior
     * @param string[] $targetUids
     * @param string[] $targetVideoIds
     * @param string[] $targetRoomIds
     * @param array<int, array<string, mixed>> $targetArchives
     * @param array<int, array<string, mixed>> $checkpoints
     */
    public function __construct(
        public readonly string $taskId,
        public readonly string $taskName,
        public readonly string $capability,
        public readonly string $supportLevel,
        public readonly array $btnBehavior = [],
        public readonly string $counter = '',
        public readonly string $jumpLink = '',
        public readonly string $jumpPosition = '',
        public readonly string $topicId = '',
        public readonly string $topicName = '',
        public readonly string $awardName = '',
        public readonly int $requiredWatchSeconds = 0,
        public readonly int $taskStatus = 0,
        public readonly int $taskType = 0,
        public readonly int $periodType = 0,
        public readonly int $taskAwardType = 0,
        public readonly array $targetUids = [],
        public readonly array $targetVideoIds = [],
        public readonly array $targetRoomIds = [],
        public readonly int $targetAreaId = 0,
        public readonly int $targetParentAreaId = 0,
        public readonly array $targetArchives = [],
        public readonly array $checkpoints = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['task_id'] ?? $data['taskId'] ?? ''),
            (string)($data['task_name'] ?? $data['taskName'] ?? ''),
            (string)($data['capability'] ?? self::CAPABILITY_UNKNOWN),
            (string)($data['support_level'] ?? self::SUPPORT_LATER),
            self::normalizeStringArray($data['btn_behavior'] ?? $data['btnBehavior'] ?? []),
            (string)($data['counter'] ?? ''),
            (string)($data['jump_link'] ?? $data['jumpLink'] ?? ''),
            (string)($data['jump_position'] ?? $data['jumpPosition'] ?? ''),
            (string)($data['topic_id'] ?? $data['topicId'] ?? ''),
            (string)($data['topic_name'] ?? $data['topicName'] ?? ''),
            (string)($data['award_name'] ?? $data['awardName'] ?? ''),
            (int)($data['required_watch_seconds'] ?? $data['requiredWatchSeconds'] ?? 0),
            (int)($data['task_status'] ?? $data['taskStatus'] ?? 0),
            (int)($data['task_type'] ?? $data['taskType'] ?? 0),
            (int)($data['period_type'] ?? $data['periodType'] ?? 0),
            (int)($data['task_award_type'] ?? $data['taskAwardType'] ?? 0),
            self::normalizeStringArray($data['target_uids'] ?? $data['targetUids'] ?? []),
            self::normalizeStringArray($data['target_video_ids'] ?? $data['targetVideoIds'] ?? []),
            self::normalizeStringArray($data['target_room_ids'] ?? $data['targetRoomIds'] ?? []),
            (int)($data['target_area_id'] ?? $data['targetAreaId'] ?? 0),
            (int)($data['target_parent_area_id'] ?? $data['targetParentAreaId'] ?? 0),
            self::normalizeCheckpointList($data['target_archives'] ?? $data['targetArchives'] ?? []),
            self::normalizeCheckpointList($data['checkpoints'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task_id' => $this->taskId,
            'task_name' => $this->taskName,
            'capability' => $this->capability,
            'support_level' => $this->supportLevel,
            'btn_behavior' => $this->btnBehavior,
            'counter' => $this->counter,
            'jump_link' => $this->jumpLink,
            'jump_position' => $this->jumpPosition,
            'topic_id' => $this->topicId,
            'topic_name' => $this->topicName,
            'award_name' => $this->awardName,
            'required_watch_seconds' => $this->requiredWatchSeconds,
            'task_status' => $this->taskStatus,
            'task_type' => $this->taskType,
            'period_type' => $this->periodType,
            'task_award_type' => $this->taskAwardType,
            'target_uids' => $this->targetUids,
            'target_video_ids' => $this->targetVideoIds,
            'target_room_ids' => $this->targetRoomIds,
            'target_area_id' => $this->targetAreaId,
            'target_parent_area_id' => $this->targetParentAreaId,
            'target_archives' => $this->targetArchives,
            'checkpoints' => $this->checkpoints,
        ];
    }

    public function isRunnableNow(): bool
    {
        return $this->supportLevel === self::SUPPORT_NOW;
    }

    public function hasLiveTarget(): bool
    {
        return $this->targetRoomIds !== [] || $this->targetAreaId > 0;
    }

    public function hasLiveAreaTarget(): bool
    {
        return $this->targetAreaId > 0;
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

    /**
     * @param mixed $value
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeCheckpointList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $checkpoints = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $checkpoints[] = $item;
        }

        return $checkpoints;
    }
}
