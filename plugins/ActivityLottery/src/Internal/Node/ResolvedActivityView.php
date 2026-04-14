<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;

final class ResolvedActivityView
{
    /**
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $eraPageSnapshot
     */
    private function __construct(
        private readonly array $activity,
        private readonly array $eraPageSnapshot,
    ) {
    }

    /**
     * 处理from流程
     * @param ActivityFlow $flow
     * @return self
     */
    public static function fromFlow(ActivityFlow $flow): self
    {
        $context = $flow->context()->toArray();
        $snapshot = is_array($context['era_page_snapshot'] ?? null)
            ? $context['era_page_snapshot']
            : [];

        return new self($flow->activity(), $snapshot);
    }

    /**
     * 判断Stable键是否满足条件
     * @return bool
     */
    public function hasStableKey(): bool
    {
        return $this->activityId() !== ''
            || $this->pageId() !== ''
            || $this->lotteryId() !== ''
            || $this->url() !== '';
    }

    /**
     * 处理activityId
     * @return string
     */
    public function activityId(): string
    {
        return $this->firstNonEmpty('activity_id');
    }

    /**
     * 处理页面Id
     * @return string
     */
    public function pageId(): string
    {
        return $this->firstNonEmpty('page_id');
    }

    /**
     * 处理抽奖Id
     * @return string
     */
    public function lotteryId(): string
    {
        $lotteryId = $this->firstNonEmpty('lottery_id');
        if ($lotteryId !== '') {
            return $lotteryId;
        }

        return trim((string)($this->activity['sid'] ?? ''));
    }

    /**
     * 处理URL
     * @return string
     */
    public function url(): string
    {
        return trim((string)($this->activity['url'] ?? ''));
    }

    /**
     * 处理title
     * @return string
     */
    public function title(): string
    {
        return $this->firstNonEmpty('title');
    }

    /**
     * 处理start时间
     * @return int
     */
    public function startTime(): int
    {
        return $this->firstPositiveInt('start_time');
    }

    /**
     * 处理end时间
     * @return int
     */
    public function endTime(): int
    {
        return $this->firstPositiveInt('end_time');
    }

    /**
     * @return array<string, mixed>
     */
    public function toActivityArray(): array
    {
        $activity = $this->activity;
        $activity['activity_id'] = $this->activityId();
        $activity['page_id'] = $this->pageId();
        $activity['lottery_id'] = $this->lotteryId();
        $activity['url'] = $this->url();
        $activity['title'] = $this->title();
        $activity['start_time'] = $this->startTime();
        $activity['end_time'] = $this->endTime();

        return $activity;
    }

    /**
     * 处理firstNonEmpty
     * @param string $field
     * @return string
     */
    private function firstNonEmpty(string $field): string
    {
        $value = trim((string)($this->activity[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return trim((string)($this->eraPageSnapshot[$field] ?? ''));
    }

    /**
     * 处理firstPositiveInt
     * @param string $field
     * @return int
     */
    private function firstPositiveInt(string $field): int
    {
        $value = (int)($this->activity[$field] ?? 0);
        if ($value > 0) {
            return $value;
        }

        $snapshotValue = (int)($this->eraPageSnapshot[$field] ?? 0);
        return max(0, $snapshotValue);
    }
}

