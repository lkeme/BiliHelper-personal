<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageSnapshot;

$catalogItem = new ActivityCatalogItem(
    'shared-activity',
    'remote-newer-title',
    '2026-04-02T08:00:00+00:00'
);
$pageSnapshot = new EraPageSnapshot(activityLotteryFixturePath('era-page-basic.html'));

$planner = new ActivityFlowPlanner();
$flow = $planner->plan($catalogItem, $pageSnapshot, '2026-04-02');
Assert::true(
    $flow instanceof ActivityFlow,
    'ActivityFlowPlanner::plan 应返回 ActivityFlow。'
);
$nodes = $flow->nodes();
Assert::true(
    is_array($nodes),
    'ActivityFlow::nodes 应返回节点数组。'
);
/* @var object|null $firstNode */
$firstNode = $nodes[0] ?? null;
Assert::true(
    $firstNode !== null && isset($firstNode->type),
    '首节点应公开 type 属性。'
);
Assert::same(
    'load_activity_snapshot',
    $firstNode->type,
    '节点序列头部应为 load_activity_snapshot。'
);
$hasRefresh = false;
foreach ($nodes as $node) {
    if (isset($node->type) && $node->type === 'refresh_draw_times') {
        $hasRefresh = true;
        break;
    }
}
Assert::true(
    $hasRefresh,
    '节点序列应包含 refresh_draw_times。'
);
