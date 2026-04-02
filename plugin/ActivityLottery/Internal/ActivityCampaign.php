<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal;

final class ActivityCampaign
{
    public function __construct(
        public readonly string $title,
        public readonly string $activityUrl = '',
        public readonly string $rewardUrl = '',
        public readonly string $recordUrl = '',
        public readonly string $taskBackend = '',
        public readonly string $drawBackend = '',
        public readonly string $activityId = '',
        public readonly string $lotteryId = '',
        public readonly string $drawId = '',
    ) {
    }

    public function hasRewardUrl(): bool
    {
        return $this->rewardUrl !== '';
    }

    public function hasRecordUrl(): bool
    {
        return $this->recordUrl !== '';
    }

    public function hasActivityUrl(): bool
    {
        return $this->activityUrl !== '';
    }

    public function hasDrawBackend(): bool
    {
        return $this->drawBackend !== '';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            trim((string)($data['title'] ?? '')),
            trim((string)($data['activity_url'] ?? $data['activityUrl'] ?? '')),
            trim((string)($data['reward_url'] ?? $data['rewardUrl'] ?? '')),
            trim((string)($data['record_url'] ?? $data['recordUrl'] ?? '')),
            trim((string)($data['task_backend'] ?? $data['taskBackend'] ?? '')),
            trim((string)($data['draw_backend'] ?? $data['drawBackend'] ?? '')),
            trim((string)($data['activity_id'] ?? $data['activityId'] ?? '')),
            trim((string)($data['lottery_id'] ?? $data['lotteryId'] ?? '')),
            trim((string)($data['draw_id'] ?? $data['drawId'] ?? '')),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'activity_url' => $this->activityUrl,
            'reward_url' => $this->rewardUrl,
            'record_url' => $this->recordUrl,
            'task_backend' => $this->taskBackend,
            'draw_backend' => $this->drawBackend,
            'activity_id' => $this->activityId,
            'lottery_id' => $this->lotteryId,
            'draw_id' => $this->drawId,
        ];
    }
}
