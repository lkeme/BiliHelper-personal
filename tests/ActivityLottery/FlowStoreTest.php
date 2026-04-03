<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Cache\Cache;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
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

$store->save([
    ActivityFlowFactory::create($catalogItem, '2026-04-03', [
        new ActivityNode('noop'),
    ]),
]);
Assert::same(1, count($store->load('2026-04-02')), '按日期加载不应混入其它 biz_date 数据。');
Assert::same(1, count($store->load('2026-04-03')), '新日期数据应可独立加载。');
