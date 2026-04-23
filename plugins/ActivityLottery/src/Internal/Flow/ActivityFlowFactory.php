<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use RuntimeException;

final class ActivityFlowFactory
{
    /**
     * @param ActivityNode[] $nodes
     */
    public static function create(ActivityCatalogItem $item, string $bizDate, array $nodes, string $phase = ''): ActivityFlow
    {
        return self::createVirtual($item->toArray(), $bizDate, $nodes, $phase);
    }

    /**
     * @param ActivityNode[] $nodes
     * @param array<string, mixed> $activity
     */
    public static function createVirtual(array $activity, string $bizDate, array $nodes, string $phase = ''): ActivityFlow
    {
        if ($nodes === []) {
            throw new RuntimeException('ActivityFlowFactory 不允许创建空节点 flow');
        }

        $now = time();
        $activity['flow_kind'] = self::resolveFlowKind($phase, $activity);
        $stableKey = self::resolveStableActivityKey($activity);
        $normalizedBizDate = ActivityFlow::normalizeBizDate($bizDate);
        $flowId = self::buildFlowId($stableKey, $normalizedBizDate, $phase);

        return new ActivityFlow(
            $flowId,
            $normalizedBizDate,
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

    /**
     * @param array<string, mixed> $activity
     */
    private static function resolveFlowKind(string $phase, array $activity): string
    {
        $kind = trim((string)($activity['flow_kind'] ?? ''));
        if ($kind !== '') {
            return $kind;
        }

        return match (trim($phase)) {
            'draw' => 'draw',
            'cleanup' => 'cleanup',
            default => 'task',
        };
    }

    /**
     * @param array<string, mixed> $activity
     */
    private static function resolveStableActivityKey(array $activity): string
    {
        foreach (['activity_id', 'page_id', 'lottery_id', 'url'] as $field) {
            $value = trim((string)($activity[$field] ?? ''));
            if ($value !== '') {
                return $field . ':' . $value;
            }
        }

        throw new RuntimeException('ActivityFlowFactory 缺少稳定唯一键，无法生成 flow');
    }

    /**
     * 构建流程Id
     * @param string $stableActivityKey
     * @param string $bizDate
     * @param string $phase
     * @return string
     */
    private static function buildFlowId(string $stableActivityKey, string $bizDate, string $phase = ''): string
    {
        $raw = trim($stableActivityKey) . '|' . trim($bizDate) . ($phase !== '' ? '|' . $phase : '');

        return substr(sha1($raw), 0, 24);
    }
}

