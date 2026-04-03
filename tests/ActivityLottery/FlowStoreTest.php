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
        'payload' => [],
        'context' => [],
        'attempts' => 0,
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

$emptyFlowIdThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'flow_id' => '',
    ]));
} catch (\RuntimeException) {
    $emptyFlowIdThrown = true;
}
Assert::true($emptyFlowIdThrown, '空 flow_id 应被拒绝。');

$emptyNodeTypeThrown = false;
try {
    new ActivityNode('');
} catch (\RuntimeException) {
    $emptyNodeTypeThrown = true;
}
Assert::true($emptyNodeTypeThrown, '空 node type 应被拒绝。');

$emptyNodesIndexThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'nodes' => [],
        'current_node_index' => 1,
    ]));
} catch (\RuntimeException) {
    $emptyNodesIndexThrown = true;
}
Assert::true($emptyNodesIndexThrown, 'nodes 为空时 current_node_index>0 应被拒绝。');

$emptyNodesInvariantThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'nodes' => [],
        'current_node_index' => 0,
    ]));
} catch (\RuntimeException) {
    $emptyNodesInvariantThrown = true;
}
Assert::true($emptyNodesInvariantThrown, 'flow nodes 为空时应被拒绝。');

$goodRow = ActivityFlowFactory::create($catalogItem, '2026-04-04', [new ActivityNode('ok')])->toArray();
Cache::set('activity_flow_day:2026-04-04', [
    $goodRow,
    array_merge($goodRow, ['flow_id' => '']),
], 'ActivityLottery');
$mixedRowsThrown = false;
$mixedRowsMessage = '';
try {
    $store->load('2026-04-04');
} catch (\RuntimeException $exception) {
    $mixedRowsThrown = true;
    $mixedRowsMessage = $exception->getMessage();
}
Assert::true($mixedRowsThrown, '混合好坏缓存行时应严格失败。');
Assert::true(
    str_contains($mixedRowsMessage, '2026-04-04') && str_contains($mixedRowsMessage, 'index=1'),
    '严格失败异常信息应包含 biz_date 与索引定位信息。'
);

$nodesContainInvalidEntryThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'nodes' => [
            $flow->nodes()[0]->toArray(),
            'not-an-array-node',
        ],
    ]));
} catch (\RuntimeException) {
    $nodesContainInvalidEntryThrown = true;
}
Assert::true($nodesContainInvalidEntryThrown, 'nodes 中混入非数组项时应严格失败。');

$invalidFlowContextThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'context' => 'invalid-context',
    ]));
} catch (\RuntimeException) {
    $invalidFlowContextThrown = true;
}
Assert::true($invalidFlowContextThrown, 'flow context 非数组时应严格失败。');

$invalidFlowLogsThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'logs' => 'invalid-logs',
    ]));
} catch (\RuntimeException) {
    $invalidFlowLogsThrown = true;
}
Assert::true($invalidFlowLogsThrown, 'flow logs 非数组时应严格失败。');

$invalidNodeResultThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-with-invalid-result',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'context' => [],
        'attempts' => 0,
        'result' => 'invalid-result',
    ]);
} catch (\RuntimeException) {
    $invalidNodeResultThrown = true;
}
Assert::true($invalidNodeResultThrown, 'node result 非法 shape 时应严格失败。');

$missingCatalogStableIdThrown = false;
try {
    $catalogWithoutStableId = ActivityCatalogItem::fromArray([
        'title' => 'no-stable-id',
        'update_time' => '2026-04-02T00:00:00+08:00',
    ]);
    ActivityFlowFactory::create($catalogWithoutStableId, '2026-04-02', [
        new ActivityNode('noop'),
    ]);
} catch (\RuntimeException) {
    $missingCatalogStableIdThrown = true;
}
Assert::true($missingCatalogStableIdThrown, 'catalog item 无稳定唯一键时不允许生成 flow。');

$canonicalFlow = new ActivityFlow(
    '  flow-canonical-1  ',
    ' 2026-04-05 ',
    ['id' => 'act-canonical'],
    '  pending  ',
    0,
    [new ActivityNode('  canonical-node  ', [], ' waiting ')],
    0,
    0,
    new \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowContext(),
    [],
    time(),
    time(),
);
Assert::same('flow-canonical-1', $canonicalFlow->id(), '构造边界应 canonicalize flow_id。');
Assert::same('2026-04-05', $canonicalFlow->bizDate(), '构造边界应 canonicalize biz_date。');
Assert::same(ActivityFlowStatus::PENDING, $canonicalFlow->status(), '构造边界应 canonicalize flow status。');
Assert::same('canonical-node', $canonicalFlow->nodes()[0]->type(), '构造边界应 canonicalize node type。');
Assert::same(ActivityNodeStatus::WAITING, $canonicalFlow->nodes()[0]->status(), '构造边界应 canonicalize node status。');

Cache::set('activity_flow_day:2026-04-06', 'broken-container', 'ActivityLottery');
$brokenContainerLoadThrown = false;
try {
    $store->load('2026-04-06');
} catch (\RuntimeException) {
    $brokenContainerLoadThrown = true;
}
Assert::true($brokenContainerLoadThrown, '顶层缓存容器为字符串时 load 应严格失败。');

Cache::set('activity_flow_day:2026-04-07', 123, 'ActivityLottery');
$brokenContainerSaveThrown = false;
try {
    $store->save([
        ActivityFlowFactory::create($catalogItem, '2026-04-07', [new ActivityNode('noop-save')]),
    ]);
} catch (\RuntimeException) {
    $brokenContainerSaveThrown = true;
}
Assert::true($brokenContainerSaveThrown, '顶层缓存容器为数字时 save 应严格失败。');

$invalidCtorNodesThrown = false;
try {
    new ActivityFlow(
        'flow-invalid-nodes',
        '2026-04-08',
        ['id' => 'act-ctor'],
        ActivityFlowStatus::PENDING,
        0,
        ['not-node'],
        0,
        0,
        new \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowContext(),
        [],
        time(),
        time(),
    );
} catch (\RuntimeException) {
    $invalidCtorNodesThrown = true;
}
Assert::true($invalidCtorNodesThrown, '构造函数应拒绝非 ActivityNode 的 nodes 项。');

$invalidCtorLogsThrown = false;
try {
    new ActivityFlow(
        'flow-invalid-logs',
        '2026-04-08',
        ['id' => 'act-ctor'],
        ActivityFlowStatus::PENDING,
        0,
        [new ActivityNode('ok-node')],
        0,
        0,
        new \Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowContext(),
        ['bad-log'],
        time(),
        time(),
    );
} catch (\RuntimeException) {
    $invalidCtorLogsThrown = true;
}
Assert::true($invalidCtorLogsThrown, '构造函数应拒绝非数组的 logs 项。');

$invalidFlowActivityThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'activity' => 'invalid-activity',
    ]));
} catch (\RuntimeException) {
    $invalidFlowActivityThrown = true;
}
Assert::true($invalidFlowActivityThrown, 'flow activity 非数组时应严格失败。');

$invalidNodePayloadThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-with-invalid-payload',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => 'invalid-payload',
        'context' => [],
        'attempts' => 0,
    ]);
} catch (\RuntimeException) {
    $invalidNodePayloadThrown = true;
}
Assert::true($invalidNodePayloadThrown, 'node payload 非数组时应严格失败。');

$invalidNodeResultPayloadThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-with-invalid-result-payload',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'context' => [],
        'attempts' => 0,
        'result' => [
            'ok' => true,
            'payload' => 'invalid-result-payload',
        ],
    ]);
} catch (\RuntimeException) {
    $invalidNodeResultPayloadThrown = true;
}
Assert::true($invalidNodeResultPayloadThrown, 'node result.payload 非数组时应严格失败。');

$invalidFlowAttemptsScalarThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'attempts' => 'oops',
    ]));
} catch (\RuntimeException) {
    $invalidFlowAttemptsScalarThrown = true;
}
Assert::true($invalidFlowAttemptsScalarThrown, 'flow attempts 为坏标量时应严格失败。');

$invalidCurrentNodeIndexScalarThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'current_node_index' => 'oops',
    ]));
} catch (\RuntimeException) {
    $invalidCurrentNodeIndexScalarThrown = true;
}
Assert::true($invalidCurrentNodeIndexScalarThrown, 'flow current_node_index 为坏标量时应严格失败。');

$invalidNodeResultOkScalarThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-with-invalid-result-ok',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'context' => [],
        'attempts' => 0,
        'result' => [
            'ok' => 'false',
            'payload' => [],
        ],
    ]);
} catch (\RuntimeException) {
    $invalidNodeResultOkScalarThrown = true;
}
Assert::true($invalidNodeResultOkScalarThrown, 'node result.ok 非 bool 时应严格失败。');

$bizDateMismatchThrown = false;
$bizDateMismatchMessage = '';
$mismatchRow = ActivityFlowFactory::create($catalogItem, '2026-04-09', [new ActivityNode('noop-mismatch')])->toArray();
Cache::set('activity_flow_day:2026-04-10', [$mismatchRow], 'ActivityLottery');
try {
    $store->load('2026-04-10');
} catch (\RuntimeException $exception) {
    $bizDateMismatchThrown = true;
    $bizDateMismatchMessage = $exception->getMessage();
}
Assert::true($bizDateMismatchThrown, '桶键与 flow biz_date 不一致时 load 应严格失败。');
Assert::true(
    str_contains($bizDateMismatchMessage, '2026-04-10') && str_contains($bizDateMismatchMessage, '2026-04-09'),
    '桶键与 flow biz_date 不一致异常应包含请求日期与行内日期。'
);

$invalidFlowIdArrayThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'flow_id' => [],
    ]));
} catch (\RuntimeException) {
    $invalidFlowIdArrayThrown = true;
}
Assert::true($invalidFlowIdArrayThrown, 'flow_id 为数组时应严格失败。');

$invalidNodeTypeArrayThrown = false;
try {
    ActivityNode::fromArray([
        'type' => [],
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'context' => [],
        'attempts' => 0,
    ]);
} catch (\RuntimeException) {
    $invalidNodeTypeArrayThrown = true;
}
Assert::true($invalidNodeTypeArrayThrown, 'node type 为数组时应严格失败。');

$invalidNodeResultMessageScalarThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-with-invalid-result-message',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'context' => [],
        'attempts' => 0,
        'result' => [
            'ok' => true,
            'message' => 123,
            'payload' => [],
        ],
    ]);
} catch (\RuntimeException) {
    $invalidNodeResultMessageScalarThrown = true;
}
Assert::true($invalidNodeResultMessageScalarThrown, 'node result.message 非 string 时应严格失败。');

$invalidCreatedAtScalarThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'created_at' => 'oops',
    ]));
} catch (\RuntimeException) {
    $invalidCreatedAtScalarThrown = true;
}
Assert::true($invalidCreatedAtScalarThrown, 'flow created_at 为坏标量时应严格失败。');

$duplicateFlowIdOnLoadThrown = false;
$duplicateFlowIdOnLoadMessage = '';
$duplicateLoadFlow = ActivityFlowFactory::create($catalogItem, '2026-04-11', [new ActivityNode('dup-load')])->toArray();
Cache::set('activity_flow_day:2026-04-11', [
    $duplicateLoadFlow,
    $duplicateLoadFlow,
], 'ActivityLottery');
try {
    $store->load('2026-04-11');
} catch (\RuntimeException $exception) {
    $duplicateFlowIdOnLoadThrown = true;
    $duplicateFlowIdOnLoadMessage = $exception->getMessage();
}
Assert::true($duplicateFlowIdOnLoadThrown, '同日桶内重复 flow_id 时 load 应严格失败。');
Assert::true(str_contains($duplicateFlowIdOnLoadMessage, 'flow_id'), '重复 flow_id 异常应包含 flow_id 关键字。');

$duplicateFlowIdOnSaveThrown = false;
$duplicateFlowIdOnSaveMessage = '';
$duplicateSaveSeed = ActivityFlowFactory::create($catalogItem, '2026-04-12', [new ActivityNode('seed')]);
$duplicateSaveFlowA = ActivityFlow::fromArray(array_merge($duplicateSaveSeed->toArray(), [
    'flow_id' => 'flow-dup-save',
]));
$duplicateSaveFlowB = ActivityFlow::fromArray(array_merge($duplicateSaveSeed->toArray(), [
    'flow_id' => 'flow-dup-save',
    'status' => ActivityFlowStatus::RUNNING,
]));
try {
    $store->save([$duplicateSaveFlowA, $duplicateSaveFlowB]);
} catch (\RuntimeException $exception) {
    $duplicateFlowIdOnSaveThrown = true;
    $duplicateFlowIdOnSaveMessage = $exception->getMessage();
}
Assert::true($duplicateFlowIdOnSaveThrown, 'save 入参同日重复 flow_id 时应严格失败。');
Assert::true(str_contains($duplicateFlowIdOnSaveMessage, 'flow_id'), 'save 重复 flow_id 异常应包含 flow_id 关键字。');

$missingActivityThrown = false;
try {
    $missingActivityPayload = $flow->toArray();
    unset($missingActivityPayload['activity']);
    ActivityFlow::fromArray($missingActivityPayload);
} catch (\RuntimeException) {
    $missingActivityThrown = true;
}
Assert::true($missingActivityThrown, 'flow activity 缺失时应严格失败。');

$nullActivityThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'activity' => null,
    ]));
} catch (\RuntimeException) {
    $nullActivityThrown = true;
}
Assert::true($nullActivityThrown, 'flow activity 为 null 时应严格失败。');

$missingNodesThrown = false;
try {
    $missingNodesPayload = $flow->toArray();
    unset($missingNodesPayload['nodes']);
    ActivityFlow::fromArray($missingNodesPayload);
} catch (\RuntimeException) {
    $missingNodesThrown = true;
}
Assert::true($missingNodesThrown, 'flow nodes 缺失时应严格失败。');

$nullNodesThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'nodes' => null,
    ]));
} catch (\RuntimeException) {
    $nullNodesThrown = true;
}
Assert::true($nullNodesThrown, 'flow nodes 为 null 时应严格失败。');

$missingContextThrown = false;
try {
    $missingContextPayload = $flow->toArray();
    unset($missingContextPayload['context']);
    ActivityFlow::fromArray($missingContextPayload);
} catch (\RuntimeException) {
    $missingContextThrown = true;
}
Assert::true($missingContextThrown, 'flow context 缺失时应严格失败。');

$nullContextThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'context' => null,
    ]));
} catch (\RuntimeException) {
    $nullContextThrown = true;
}
Assert::true($nullContextThrown, 'flow context 为 null 时应严格失败。');

$missingLogsThrown = false;
try {
    $missingLogsPayload = $flow->toArray();
    unset($missingLogsPayload['logs']);
    ActivityFlow::fromArray($missingLogsPayload);
} catch (\RuntimeException) {
    $missingLogsThrown = true;
}
Assert::true($missingLogsThrown, 'flow logs 缺失时应严格失败。');

$nullLogsThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'logs' => null,
    ]));
} catch (\RuntimeException) {
    $nullLogsThrown = true;
}
Assert::true($nullLogsThrown, 'flow logs 为 null 时应严格失败。');

$missingNodePayloadThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-missing-payload',
        'status' => ActivityNodeStatus::PENDING,
        'context' => [],
        'attempts' => 0,
    ]);
} catch (\RuntimeException) {
    $missingNodePayloadThrown = true;
}
Assert::true($missingNodePayloadThrown, 'node payload 缺失时应严格失败。');

$nullNodePayloadThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-null-payload',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => null,
        'context' => [],
        'attempts' => 0,
    ]);
} catch (\RuntimeException) {
    $nullNodePayloadThrown = true;
}
Assert::true($nullNodePayloadThrown, 'node payload 为 null 时应严格失败。');

$missingNodeContextThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-missing-context',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'attempts' => 0,
    ]);
} catch (\RuntimeException) {
    $missingNodeContextThrown = true;
}
Assert::true($missingNodeContextThrown, 'node context 缺失时应严格失败。');

$nullNodeContextThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-null-context',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'context' => null,
        'attempts' => 0,
    ]);
} catch (\RuntimeException) {
    $nullNodeContextThrown = true;
}
Assert::true($nullNodeContextThrown, 'node context 为 null 时应严格失败。');

$missingResultPayloadThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-missing-result-payload',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'context' => [],
        'attempts' => 0,
        'result' => [
            'ok' => true,
            'message' => 'ok',
        ],
    ]);
} catch (\RuntimeException) {
    $missingResultPayloadThrown = true;
}
Assert::true($missingResultPayloadThrown, 'node result.payload 缺失时应严格失败。');

$nullResultPayloadThrown = false;
try {
    ActivityNode::fromArray([
        'type' => 'node-null-result-payload',
        'status' => ActivityNodeStatus::PENDING,
        'payload' => [],
        'context' => [],
        'attempts' => 0,
        'result' => [
            'ok' => true,
            'message' => 'ok',
            'payload' => null,
        ],
    ]);
} catch (\RuntimeException) {
    $nullResultPayloadThrown = true;
}
Assert::true($nullResultPayloadThrown, 'node result.payload 为 null 时应严格失败。');

$invalidBizDateFromArrayThrown = false;
try {
    ActivityFlow::fromArray(array_merge($flow->toArray(), [
        'biz_date' => '2026/04/02',
    ]));
} catch (\RuntimeException) {
    $invalidBizDateFromArrayThrown = true;
}
Assert::true($invalidBizDateFromArrayThrown, '非法格式 biz_date 在 fromArray 中应被拒绝。');

$invalidBizDateFactoryThrown = false;
try {
    ActivityFlowFactory::create($catalogItem, '2026/04/02', [
        new ActivityNode('invalid-biz-date'),
    ]);
} catch (\RuntimeException) {
    $invalidBizDateFactoryThrown = true;
}
Assert::true($invalidBizDateFactoryThrown, '非法格式 biz_date 在工厂创建中应被拒绝。');

$emptyNodesFactoryThrown = false;
try {
    ActivityFlowFactory::create($catalogItem, '2026-04-13', []);
} catch (\RuntimeException) {
    $emptyNodesFactoryThrown = true;
}
Assert::true($emptyNodesFactoryThrown, '工厂创建不应接受空节点流。');

$invalidBizDateOnLoadThrown = false;
try {
    $store->load('2026/04/02');
} catch (\RuntimeException) {
    $invalidBizDateOnLoadThrown = true;
}
Assert::true($invalidBizDateOnLoadThrown, 'load 非法格式 biz_date 时应直接抛出异常。');
