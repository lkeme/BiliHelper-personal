<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Page;

final class EraTaskSnapshot
{
    /**
     * @param string[] $targetUids
     * @param string[] $targetVideoIds
     * @param string[] $targetRoomIds
     * @param array<int, array<string, mixed>> $targetArchives
     * @param array<int, array<string, mixed>> $checkpoints
     * @param string[] $btnBehavior
     */
    public function __construct(
        private readonly string $taskId,
        private readonly string $taskName,
        private readonly string $capability,
        private readonly string $supportLevel,
        private readonly string $counter,
        private readonly string $jumpLink,
        private readonly string $topicId,
        private readonly string $awardName,
        private readonly int $requiredWatchSeconds,
        private readonly array $targetUids,
        private readonly array $targetVideoIds,
        private readonly array $targetRoomIds,
        private readonly array $targetArchives,
        private readonly int $targetAreaId,
        private readonly int $targetParentAreaId,
        private readonly array $checkpoints,
        private readonly array $btnBehavior,
        private readonly int $taskStatus,
        private readonly int $taskAwardType,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            trim((string)($data['task_id'] ?? $data['taskId'] ?? '')),
            trim((string)($data['task_name'] ?? $data['taskName'] ?? '')),
            trim((string)($data['capability'] ?? '')),
            trim((string)($data['support_level'] ?? $data['supportLevel'] ?? 'later')),
            trim((string)($data['counter'] ?? '')),
            trim((string)($data['jump_link'] ?? $data['jumpLink'] ?? '')),
            trim((string)($data['topic_id'] ?? $data['topicId'] ?? $data['topicID'] ?? '')),
            trim((string)($data['award_name'] ?? $data['awardName'] ?? '')),
            (int)($data['required_watch_seconds'] ?? $data['requiredWatchSeconds'] ?? 0),
            self::normalizeStringArray($data['target_uids'] ?? $data['targetUids'] ?? []),
            self::normalizeStringArray($data['target_video_ids'] ?? $data['targetVideoIds'] ?? []),
            self::normalizeStringArray($data['target_room_ids'] ?? $data['targetRoomIds'] ?? []),
            self::normalizeArchiveList($data['target_archives'] ?? $data['targetArchives'] ?? []),
            (int)($data['target_area_id'] ?? $data['targetAreaId'] ?? 0),
            (int)($data['target_parent_area_id'] ?? $data['targetParentAreaId'] ?? 0),
            self::normalizeCheckpointList($data['checkpoints'] ?? []),
            self::normalizeStringArray($data['btn_behavior'] ?? $data['btnBehavior'] ?? []),
            (int)($data['task_status'] ?? $data['taskStatus'] ?? 0),
            (int)($data['task_award_type'] ?? $data['taskAwardType'] ?? 0),
        );
    }

    public function taskId(): string
    {
        return $this->taskId;
    }

    public function taskName(): string
    {
        return $this->taskName;
    }

    public function capability(): string
    {
        return $this->capability;
    }

    public function supportLevel(): string
    {
        return $this->supportLevel;
    }

    public function counter(): string
    {
        return $this->counter;
    }

    public function jumpLink(): string
    {
        return $this->jumpLink;
    }

    public function topicId(): string
    {
        return $this->topicId;
    }

    public function awardName(): string
    {
        return $this->awardName;
    }

    public function requiredWatchSeconds(): int
    {
        return $this->requiredWatchSeconds;
    }

    /**
     * @return string[]
     */
    public function targetUids(): array
    {
        return $this->targetUids;
    }

    /**
     * @return string[]
     */
    public function targetVideoIds(): array
    {
        return $this->targetVideoIds;
    }

    /**
     * @return string[]
     */
    public function targetRoomIds(): array
    {
        return $this->targetRoomIds;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function targetArchives(): array
    {
        return $this->targetArchives;
    }

    public function targetAreaId(): int
    {
        return $this->targetAreaId;
    }

    public function targetParentAreaId(): int
    {
        return $this->targetParentAreaId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function checkpoints(): array
    {
        return $this->checkpoints;
    }

    /**
     * @return string[]
     */
    public function btnBehavior(): array
    {
        return $this->btnBehavior;
    }

    public function taskStatus(): int
    {
        return $this->taskStatus;
    }

    public function taskAwardType(): int
    {
        return $this->taskAwardType;
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
            'counter' => $this->counter,
            'jump_link' => $this->jumpLink,
            'topic_id' => $this->topicId,
            'award_name' => $this->awardName,
            'required_watch_seconds' => $this->requiredWatchSeconds,
            'target_uids' => $this->targetUids,
            'target_video_ids' => $this->targetVideoIds,
            'target_room_ids' => $this->targetRoomIds,
            'target_archives' => $this->targetArchives,
            'target_area_id' => $this->targetAreaId,
            'target_parent_area_id' => $this->targetParentAreaId,
            'checkpoints' => $this->checkpoints,
            'btn_behavior' => $this->btnBehavior,
            'task_status' => $this->taskStatus,
            'task_award_type' => $this->taskAwardType,
        ];
    }

    /**
     * @return string[]
     */
    private static function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item)) {
                continue;
            }

            $label = trim((string)$item);
            if ($label === '') {
                continue;
            }

            $normalized[] = $label;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeCheckpointList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $checkpoints = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $checkpoints[] = $item;
            }
        }

        return $checkpoints;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeArchiveList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $archives = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $archives[] = $item;
            }
        }

        return $archives;
    }
}

