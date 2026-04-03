<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;

final class ActivityFlowFactory
{
    /**
     * @param ActivityNode[] $nodes
     */
    public static function create(ActivityCatalogItem $item, string $bizDate, array $nodes): ActivityFlow
    {
        $now = time();
        $activity = $item->toArray();
        $flowId = self::buildFlowId($activity['id'] ?? '', $bizDate);

        return new ActivityFlow(
            $flowId,
            $bizDate,
            $activity,
            ActivityFlowStatus::PENDING,
            0,
            $nodes,
            0,
            0,
            new ActivityFlowContext(),
            [],
            $now,
            $now,
        );
    }

    private static function buildFlowId(string $activityId, string $bizDate): string
    {
        $raw = trim($activityId) . '|' . trim($bizDate);

        return substr(sha1($raw), 0, 24);
    }
}
