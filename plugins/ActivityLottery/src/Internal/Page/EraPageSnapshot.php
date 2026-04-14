<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Page;

final class EraPageSnapshot
{
    /**
     * @param EraTaskSnapshot[] $tasks
     */
    public function __construct(
        private readonly string $title,
        private readonly string $pageId,
        private readonly string $activityId,
        private readonly string $lotteryId,
        private readonly int $startTime,
        private readonly int $endTime,
        private readonly array $tasks,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $tasks = [];
        foreach (($data['tasks'] ?? []) as $task) {
            if ($task instanceof EraTaskSnapshot) {
                $tasks[] = $task;
                continue;
            }

            if (is_array($task)) {
                $tasks[] = EraTaskSnapshot::fromArray($task);
            }
        }

        return new self(
            trim((string)($data['title'] ?? '')),
            trim((string)($data['page_id'] ?? $data['pageId'] ?? '')),
            trim((string)($data['activity_id'] ?? $data['activityId'] ?? '')),
            trim((string)($data['lottery_id'] ?? $data['lotteryId'] ?? '')),
            (int)($data['start_time'] ?? $data['startTime'] ?? 0),
            (int)($data['end_time'] ?? $data['endTime'] ?? 0),
            $tasks,
        );
    }

    /**
     * 处理title
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * 处理页面Id
     * @return string
     */
    public function pageId(): string
    {
        return $this->pageId;
    }

    /**
     * 处理activityId
     * @return string
     */
    public function activityId(): string
    {
        return $this->activityId;
    }

    /**
     * 处理抽奖Id
     * @return string
     */
    public function lotteryId(): string
    {
        return $this->lotteryId;
    }

    /**
     * 处理start时间
     * @return int
     */
    public function startTime(): int
    {
        return $this->startTime;
    }

    /**
     * 处理end时间
     * @return int
     */
    public function endTime(): int
    {
        return $this->endTime;
    }

    /**
     * @return EraTaskSnapshot[]
     */
    public function tasks(): array
    {
        return $this->tasks;
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
            'tasks' => array_map(
                static fn (EraTaskSnapshot $task): array => $task->toArray(),
                $this->tasks,
            ),
        ];
    }
}


