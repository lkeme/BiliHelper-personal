<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageSnapshot;

$catalogItem = new ActivityCatalogItem(
    'shared-activity',
    'remote-newer-title',
    '2026-04-02T08:00:00+00:00'
);
$pageSnapshot = new EraPageSnapshot(activityLotteryFixturePath('era-page-basic.html'));
Assert::true(
    method_exists($pageSnapshot, 'content'),
    'EraPageSnapshot 需要提供 content 方法。'
);

$planner = new ActivityFlowPlanner();
$flow = $planner->plan($catalogItem, $pageSnapshot, '2026-04-02');
Assert::true(
    method_exists($flow, 'nodeSequence'),
    'ActivityFlow 应提供 nodeSequence。'
);
$nodes = $flow->nodeSequence();
Assert::same(
    'load_activity_snapshot',
    $nodes[0] ?? null,
    '节点序列头部应为 load_activity_snapshot。'
);
Assert::true(
    in_array('refresh_draw_times', $nodes, true),
    '节点序列应包含 refresh_draw_times。'
);
