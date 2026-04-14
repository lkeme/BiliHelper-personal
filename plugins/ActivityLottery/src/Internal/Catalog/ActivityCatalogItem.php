<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog;

final class ActivityCatalogItem
{
    /**
     * 初始化 ActivityCatalogItem
     * @param string $id
     * @param string $title
     * @param string $updateTime
     * @param string $activityId
     * @param string $pageId
     * @param string $lotteryId
     * @param string $url
     * @param int $startTime
     * @param int $endTime
     */
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly string $updateTime = '',
        private readonly string $activityId = '',
        private readonly string $pageId = '',
        private readonly string $lotteryId = '',
        private readonly string $url = '',
        private readonly int $startTime = 0,
        private readonly int $endTime = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $activityId = trim((string)($data['activity_id'] ?? $data['activityId'] ?? ''));
        $pageId = trim((string)($data['page_id'] ?? $data['pageId'] ?? ''));
        $lotteryId = trim((string)($data['lottery_id'] ?? $data['lotteryId'] ?? ''));
        $url = trim((string)($data['url'] ?? ''));
        $fallbackId = trim((string)($data['id'] ?? ''));
        $id = self::resolveUniqueKey($activityId, $pageId, $lotteryId, $url, $fallbackId);

        return new self(
            $id,
            trim((string)($data['title'] ?? '')),
            trim((string)($data['update_time'] ?? $data['updateTime'] ?? '')),
            $activityId,
            $pageId,
            $lotteryId,
            $url,
            (int)($data['start_time'] ?? $data['startTime'] ?? 0),
            (int)($data['end_time'] ?? $data['endTime'] ?? 0),
        );
    }

    /**
     * 处理id
     * @return string
     */
    public function id(): string
    {
        return $this->id;
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
     * 保存或更新时间
     * @return string
     */
    public function updateTime(): string
    {
        return $this->updateTime;
    }

    /**
     * 保存或更新时间戳
     * @return int
     */
    public function updateTimestamp(): int
    {
        $timestamp = strtotime($this->updateTime);
        return $timestamp === false ? 0 : $timestamp;
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
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'update_time' => $this->updateTime,
            'activity_id' => $this->activityId,
            'page_id' => $this->pageId,
            'lottery_id' => $this->lotteryId,
            'url' => $this->url,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }

    /**
     * 解析Unique键
     * @param string $activityId
     * @param string $pageId
     * @param string $lotteryId
     * @param string $url
     * @param string $fallbackId
     * @return string
     */
    private static function resolveUniqueKey(
        string $activityId,
        string $pageId,
        string $lotteryId,
        string $url,
        string $fallbackId,
    ): string {
        if ($activityId !== '') {
            return $activityId;
        }
        if ($pageId !== '') {
            return $pageId;
        }
        if ($lotteryId !== '') {
            return $lotteryId;
        }
        if ($url !== '') {
            return $url;
        }

        return $fallbackId;
    }
}

