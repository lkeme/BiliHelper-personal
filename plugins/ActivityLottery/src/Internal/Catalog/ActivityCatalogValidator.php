<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog;

final class ActivityCatalogValidator
{
    private \Closure $logger;

    /**
     * 初始化 ActivityCatalogValidator
     * @param callable $logger
     */
    public function __construct(?callable $logger = null)
    {
        $this->logger = $logger !== null
            ? \Closure::fromCallable($logger)
            : static function (string $level, string $message, array $context = []): void {
            };
    }

    /**
     * @param ActivityCatalogItem[] $items
     * @return ActivityCatalogItem[]
     */
    public function validate(array $items): array
    {
        $valid = [];
        foreach ($items as $item) {
            if (!$item instanceof ActivityCatalogItem) {
                continue;
            }

            $row = $item->toArray();
            $reason = $this->rejectReason($item);
            if ($reason !== null) {
                $this->log('warning', sprintf('活动目录校验过滤活动「%s」: %s', $item->title(), $reason), [
                    'event' => 'catalog.invalid_item',
                    'activity_id' => (string)($row['activity_id'] ?? ''),
                    'page_id' => (string)($row['page_id'] ?? ''),
                    'lottery_id' => (string)($row['lottery_id'] ?? ''),
                    'url' => (string)($row['url'] ?? ''),
                    'reason' => $reason,
                ]);
                continue;
            }

            $valid[] = $item;
        }

        return $valid;
    }

    /**
     * 处理rejectReason
     * @param ActivityCatalogItem $item
     * @return ?string
     */
    private function rejectReason(ActivityCatalogItem $item): ?string
    {
        $row = $item->toArray();
        if ($item->id() === '') {
            return '缺少稳定唯一键';
        }

        if (trim((string)($row['url'] ?? '')) === '') {
            return '缺少活动 URL';
        }

        $startTime = (int)($row['start_time'] ?? 0);
        $endTime = (int)($row['end_time'] ?? 0);
        if ($startTime > 0 && $endTime > 0 && $startTime >= $endTime) {
            return 'start_time/end_time 非法';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}

