<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\EraActivityPage;
use Bhp\Plugin\ActivityLottery\Internal\EraActivityTask;

$catalogItem = ActivityCatalogItem::fromArray([
    'id' => 'shared-activity',
    'activity_id' => 'act-flow-planner',
    'title' => 'remote-newer-title',
    'update_time' => '2026-04-02T08:00:00+00:00',
]);

$planner = new ActivityFlowPlanner();
$flow = $planner->plan($catalogItem, null, '2026-04-02');
Assert::true(
    $flow instanceof ActivityFlow,
    'ActivityFlowPlanner::plan 应返回 ActivityFlow。'
);
$nodes = $flow->nodes();
Assert::true(
    is_array($nodes),
    'ActivityFlow::nodes 应返回节点数组。'
);
/* @var ActivityNode|null $firstNode */
$firstNode = $nodes[0] ?? null;
Assert::true(
    $firstNode instanceof ActivityNode,
    '首节点应为 ActivityNode。'
);
Assert::same(
    'load_activity_snapshot',
    $firstNode->type(),
    '节点序列头部应为 load_activity_snapshot。'
);

$refreshNodeIndex = -1;
$hasRefresh = false;
foreach ($nodes as $index => $node) {
    if ($node->type() === 'refresh_draw_times') {
        $hasRefresh = true;
        $refreshNodeIndex = (int)$index;
        break;
    }
}
Assert::true(
    $hasRefresh,
    '节点序列应包含 refresh_draw_times。'
);
Assert::true(
    $refreshNodeIndex >= 0 && $refreshNodeIndex <= count($nodes) - 4,
    'refresh_draw_times 应位于抽奖阶段尾部起点之前。'
);

$dynamicSnapshot = (object)[
    'tasks' => [
        ['task_id' => 't-follow', 'capability' => 'follow'],
        ['task_id' => 't-unfollow', 'capability' => 'unfollow'],
        ['task_id' => 't-unknown', 'capability' => 'unknown'],
    ],
];
$flowWithDynamic = $planner->plan($catalogItem, $dynamicSnapshot, '2026-04-02');
$dynamicTypes = array_map(
    static fn (ActivityNode $node): string => $node->type(),
    $flowWithDynamic->nodes(),
);
Assert::true(
    in_array('era_task_follow', $dynamicTypes, true),
    '动态 ERA 能力应映射为 era_task_follow 节点。'
);
Assert::true(
    in_array('era_task_unfollow', $dynamicTypes, true),
    '动态 ERA 能力应映射为 era_task_unfollow 节点。'
);
Assert::false(
    in_array('era_task_unknown', $dynamicTypes, true),
    '未知能力不应生成节点。'
);

$pageWithTaskObjects = EraActivityPage::fromArray([
    'title' => 'era-page',
    'page_id' => 'p1',
    'activity_id' => 'act-flow-planner',
    'lottery_id' => 'l1',
    'start_time' => 0,
    'end_time' => 0,
    'tasks' => [
        [
            'task_id' => 'obj-follow',
            'task_name' => '关注任务',
            'capability' => EraActivityTask::CAPABILITY_FOLLOW,
            'task_status' => 2,
        ],
        [
            'task_id' => 'obj-manual',
            'task_name' => '手动任务',
            'capability' => EraActivityTask::CAPABILITY_MANUAL,
            'task_status' => 1,
        ],
    ],
]);
$flowWithTaskObjects = $planner->plan($catalogItem, $pageWithTaskObjects, '2026-04-02');
$objectDynamicNodes = array_values(array_filter(
    $flowWithTaskObjects->nodes(),
    static fn (ActivityNode $node): bool => str_starts_with($node->type(), 'era_task_'),
));
Assert::same(1, count($objectDynamicNodes), 'EraActivityTask 对象输入应生成可识别能力的动态节点。');
Assert::same('era_task_follow', $objectDynamicNodes[0]->type(), '对象任务 capability=follow 应映射为 era_task_follow。');
Assert::same('obj-follow', (string)($objectDynamicNodes[0]->payload()['task_id'] ?? ''), '动态节点应携带对象任务 task_id。');
Assert::same('follow', (string)($objectDynamicNodes[0]->payload()['capability'] ?? ''), '动态节点应携带对象任务 capability。');
Assert::false(
    array_key_exists('task_status', $objectDynamicNodes[0]->payload()),
    '动态节点 payload 不应携带 task_status。'
);

$flowWithGenericObjectTask = $planner->plan(
    $catalogItem,
    (object)[
        'tasks' => [
            (object)[
                'task_id' => 'generic-follow',
                'capability' => EraActivityTask::CAPABILITY_FOLLOW,
                'task_status' => 1,
            ],
        ],
    ],
    '2026-04-02',
);
$genericDynamicNodes = array_values(array_filter(
    $flowWithGenericObjectTask->nodes(),
    static fn (ActivityNode $node): bool => str_starts_with($node->type(), 'era_task_'),
));
Assert::same(0, count($genericDynamicNodes), '非 EraActivityTask 的 object 任务不应生成动态节点。');
