<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Cache\Cache;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowFactory;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStore;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Tests\Support\Assert;

if (!defined('PROFILE_CACHE_PATH')) {
    $cachePath = sys_get_temp_dir() . '/bhp-activity-lottery-tests-cache/';
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0777, true);
    }
    define('PROFILE_CACHE_PATH', $cachePath);
}

Cache::clearAll();

$catalogItem = ActivityCatalogItem::fromArray([
    'id' => 'act-fallback',
    'activity_id' => 'act-20260402',
    'title' => 'flow-store-test',
    'update_time' => '2026-04-02T00:00:00+08:00',
]);
$catalogItem2 = ActivityCatalogItem::fromArray([
    'id' => 'act-fallback-2',
    'activity_id' => 'act-20260402-2',
    'title' => 'flow-store-test-2',
    'update_time' => '2026-04-02T00:00:00+08:00',
]);
$nodes = [
    new ActivityNode('load_activity_snapshot', ['step' => 1]),
    new ActivityNode('refresh_draw_times', ['step' => 2], ActivityNodeStatus::WAITING, [
        'wait_reason' => 'sleep',
    ]),
];

$flow = ActivityFlowFactory::create($catalogItem, '2026-04-02', $nodes);
Assert::same(ActivityFlowStatus::PENDING, $flow->status(), '新建 Flow 默认应为 pending。');
Assert::same(2, count($flow->nodes()), 'Flow 应包含传入节点。');

$store = new ActivityFlowStore('ActivityLottery');
$store->save([$flow]);
$loaded = $store->load('2026-04-02');

Assert::same(1, count($loaded), '应能按 biz_date 加载出保存的 Flow。');
Assert::same($flow->id(), $loaded[0]->id(), '加载出的 Flow id 应与保存前一致。');
Assert::same('act-20260402', $loaded[0]->activity()['id'] ?? '', 'activity 最小字段应保留。');
Assert::same(
    ActivityNodeStatus::WAITING,
    $loaded[0]->nodes()[1]->status(),
    '节点状态应可持久化并恢复。'
);

$flow2 = ActivityFlowFactory::create($catalogItem2, '2026-04-02', [
    new ActivityNode('noop-2'),
]);
$store->save([$flow, $flow2]);
$flowUpdated = ActivityFlow::fromArray(array_merge($flow->toArray(), [
    'status' => ActivityFlowStatus::RUNNING,
    'attempts' => 3,
]));
$store->save([$flowUpdated]);
$loadedAfterUpdate = $store->load('2026-04-02');
Assert::same(2, count($loadedAfterUpdate), '同日增量保存更新单条时不应覆盖其他 flow。');
$updatedMatched = false;
foreach ($loadedAfterUpdate as $loadedFlow) {
    if ($loadedFlow->id() === $flow->id()) {
        $updatedMatched = true;
        Assert::same(ActivityFlowStatus::RUNNING, $loadedFlow->status(), '同 flow_id 应按最新值覆盖。');
        Assert::same(3, $loadedFlow->attempts(), '更新后的 attempts 应可持久化。');
    }
}
Assert::true($updatedMatched, '更新目标 flow 应可被加载定位。');

$store->save([
    ActivityFlowFactory::create($catalogItem, '2026-04-03', [
        new ActivityNode('noop'),
    ]),
]);
Assert::same(2, count($store->load('2026-04-02')), '按日期加载不应混入其它 biz_date 数据。');
Assert::same(1, count($store->load('2026-04-03')), '新日期数据应可独立加载。');

$invalidFlowStatusThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'status' => 'invalid-status',
    ]));
} catch (\RuntimeException) {
    $invalidFlowStatusThrown = true;
}
Assert::true($invalidFlowStatusThrown, '非法 flow 状态应抛出异常。');

$invalidNodeStatusThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'x',
        'status' => 'invalid-status',
    ]);
} catch (\RuntimeException) {
    $invalidNodeStatusThrown = true;
}
Assert::true($invalidNodeStatusThrown, '非法 node 状态应抛出异常。');

$invalidNodeIndexThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'current_node_index' => 10,
    ]));
} catch (\RuntimeException) {
    $invalidNodeIndexThrown = true;
}
Assert::true($invalidNodeIndexThrown, 'current_node_index 越界应抛出异常。');

$negativeAttemptsThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'attempts' => -1,
    ]));
} catch (\RuntimeException) {
    $negativeAttemptsThrown = true;
}
Assert::true($negativeAttemptsThrown, 'flow attempts 为负数应抛出异常。');
