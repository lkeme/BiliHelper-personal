<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowFactory;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowBudget;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowPicker;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowPool;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityLaneLimiter;

$budget = new ActivityFlowBudget(4, 6, 3000);
Assert::same(4, $budget->maxFlowsPerTick(), '预算应暴露 max_flows_per_tick。');
Assert::same(6, $budget->maxStepsPerTick(), '预算应暴露 max_steps_per_tick。');
Assert::same(3000, $budget->maxRuntimeMsPerTick(), '预算应暴露 max_runtime_ms_per_tick。');

$picker = new ActivityFlowPicker();
$laneLimiter = new ActivityLaneLimiter([
    'task_status' => 0,
    'draw_execute' => 10,
]);
$pool = new ActivityFlowPool($budget, $picker, $laneLimiter);

$flowA = buildFlow('pool-a', 'task_status');
$flowB = buildFlow('pool-b', 'task_status');
$flowC = buildFlow('pool-c', 'task_status');

$batch1 = $pool->pick([$flowA, $flowB, $flowC], 100);
Assert::same(3, count($batch1), '可推进 flow 小于预算时应全部入选。');

$limitedBudgetPool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$limitedTick1 = (int)(microtime(true) * 1000);
$limitedBatch1 = $limitedBudgetPool->pick([$flowA, $flowB, $flowC], 100, $limitedTick1);
Assert::same(2, count($limitedBatch1), '单轮入选 flow 不应超过 max_flows_per_tick。');
Assert::same($flowA->id(), $limitedBatch1[0]->id(), '第一轮应从头开始挑选。');
Assert::same($flowB->id(), $limitedBatch1[1]->id(), '第一轮应按顺序继续挑选。');
$limitedBatch2 = $limitedBudgetPool->pick([$flowA, $flowB, $flowC], 100, $limitedTick1 + 1);
Assert::same($flowC->id(), $limitedBatch2[0]->id(), '下一轮应从上轮游标后继续，保证公平推进。');
Assert::same($flowA->id(), $limitedBatch2[1]->id(), '游标应环形前进，避免单流连续吃满预算。');

$drawFlow = buildFlow('pool-draw', 'draw_execute');
$statusFlow = buildFlow('pool-status', 'task_status');
$laneLimiter->reserve('draw_execute', 100);
$laneBatch = $pool->pick([$drawFlow, $statusFlow], 105);
Assert::same(1, count($laneBatch), '被车道限速阻塞的 flow 本轮不应入选。');
Assert::same($statusFlow->id(), $laneBatch[0]->id(), '未阻塞车道的 flow 应可正常入选。');

$blockedFirstLimiter = new ActivityLaneLimiter(['draw_execute' => 10, 'task_status' => 0]);
$blockedFirstLimiter->reserve('draw_execute', 100);
$blockedFirstPool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    $blockedFirstLimiter,
);
$blockedFlow = buildFlow('pool-blocked-first', 'draw_execute');
$readyFlow1 = buildFlow('pool-ready-1', 'task_status');
$readyFlow2 = buildFlow('pool-ready-2', 'task_status');
$blockedFirstBatch = $blockedFirstPool->pick([$blockedFlow, $readyFlow1, $readyFlow2], 100);
Assert::same(2, count($blockedFirstBatch), 'budget=2 且首条 flow 被限速时，不应占用预算位。');
$pickedIds = array_map(static fn (ActivityFlow $flow): string => $flow->id(), $blockedFirstBatch);
Assert::false(in_array($blockedFlow->id(), $pickedIds, true), '被阻塞 flow 应被跳过，不得占预算位。');
Assert::true(in_array($readyFlow1->id(), $pickedIds, true), '后续 ready flow 应可入选。');
Assert::true(in_array($readyFlow2->id(), $pickedIds, true), '预算应留给后续 ready flow。');

$futureFlow = buildFlow('pool-future', 'task_status', 999);
$futureBatch = $pool->pick([$futureFlow], 100);
Assert::same(0, count($futureBatch), 'next_run_at 未到期的 flow 不应入选。');

$sameTickPool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$sameTickFlowA = buildFlow('same-tick-a', 'task_status');
$sameTickFlowB = buildFlow('same-tick-b', 'task_status');
$sameTickFlowC = buildFlow('same-tick-c', 'task_status');
$sameTickFlowD = buildFlow('same-tick-d', 'task_status');
$sameTickStartedAtMs = (int)(microtime(true) * 1000);
$sameTickBatch1 = $sameTickPool->pick([$sameTickFlowA, $sameTickFlowB, $sameTickFlowC, $sameTickFlowD], 100, $sameTickStartedAtMs);
Assert::same(2, count($sameTickBatch1), '同一 tick 第一轮应按预算选出 flow。');
$sameTickBatch2 = $sameTickPool->pick([$sameTickFlowA, $sameTickFlowB, $sameTickFlowC, $sameTickFlowD], 100, $sameTickStartedAtMs);
Assert::same(0, count($sameTickBatch2), '同一 tick 第二轮不应再次透支预算。');

$unknownLanePool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$unknownLaneThrown = false;
try {
    $unknownLanePool->pick([buildFlow('unknown-lane', 'unknown_lane')], 100);
} catch (\RuntimeException $e) {
    $unknownLaneThrown = str_contains($e->getMessage(), '未知 lane');
}
Assert::true($unknownLaneThrown, '未知 lane 应触发异常，不应静默降级。');

$unknownNodeTypePool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$unknownNodeTypeFlow = buildFlowWithType('unknown-node-type', 'mystery_node', []);
$unknownNodeTypeThrown = false;
try {
    $unknownNodeTypePool->pick([$unknownNodeTypeFlow], 100);
} catch (\RuntimeException $e) {
    $unknownNodeTypeThrown = str_contains($e->getMessage(), '未知 node type');
}
Assert::true($unknownNodeTypeThrown, '未知 node type 应触发异常，不应静默归类。');

$unknownNodeTypeWithLanePool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$unknownNodeTypeWithLaneFlow = buildFlowWithType('unknown-node-type-with-lane', 'mystery_node', ['lane' => 'task_status']);
$unknownNodeTypeWithLaneThrown = false;
try {
    $unknownNodeTypeWithLanePool->pick([$unknownNodeTypeWithLaneFlow], 100);
} catch (\RuntimeException $e) {
    $unknownNodeTypeWithLaneThrown = str_contains($e->getMessage(), '未知 node type');
}
Assert::true($unknownNodeTypeWithLaneThrown, '未知 node type 即使显式携带 lane 也应触发异常。');

$planner = new ActivityFlowPlanner();
$plannerCatalog = ActivityCatalogItem::fromArray([
    'id' => 'planner-pool-integration',
    'activity_id' => 'planner-pool-integration',
    'title' => 'planner-pool-integration',
    'update_time' => '2026-04-02T08:00:00+00:00',
]);
$dynamicFlow = $planner->plan(
    $plannerCatalog,
    (object)[
        'tasks' => [
            ['task_id' => 'follow-1', 'capability' => 'follow'],
        ],
    ],
    '2026-04-02',
);
$followNodeIndex = findNodeIndexByType($dynamicFlow, 'era_task_follow');
Assert::true($followNodeIndex >= 0, 'planner 应产出 era_task_follow 动态节点。');
$readyFollowFlow = moveFlowToNodeIndex($dynamicFlow, $followNodeIndex);
$dynamicPool = new ActivityFlowPool(
    new ActivityFlowBudget(1, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['follow' => 0, 'task_status' => 0]),
);
$dynamicBatch = $dynamicPool->pick([$readyFollowFlow], 100);
Assert::same(1, count($dynamicBatch), 'planner 产出的 era_task_follow 节点应可被 pool 正常选中。');
Assert::same($readyFollowFlow->id(), $dynamicBatch[0]->id(), '动态 follow flow 应成功进入调度结果。');

$unsupportedFlow = $planner->plan(
    $plannerCatalog,
    (object)[
        'tasks' => [
            ['task_id' => 'unsupported-1', 'capability' => 'coin_topic'],
        ],
    ],
    '2026-04-02',
);
$skippedNodeIndex = findNodeIndexByType($unsupportedFlow, 'era_task_skipped');
Assert::true($skippedNodeIndex >= 0, 'unsupported capability 应生成 skipped 动态节点。');
$readySkippedFlow = moveFlowToNodeIndex($unsupportedFlow, $skippedNodeIndex);
$skippedPool = new ActivityFlowPool(
    new ActivityFlowBudget(1, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$skippedBatch = $skippedPool->pick([$readySkippedFlow], 100);
Assert::same(1, count($skippedBatch), 'skipped 节点进入 flow 后应可被 pool 接受而非因映射不一致报错。');
Assert::same($readySkippedFlow->id(), $skippedBatch[0]->id(), '含 skipped 节点的 flow 应可完成调度选择。');

/**
 * @return ActivityFlow
 */
function buildFlow(string $activityId, string $lane, int $nextRunAt = 0): ActivityFlow
{
    $nodeType = match ($lane) {
        'draw_execute' => 'execute_draw',
        'draw_refresh' => 'refresh_draw_times',
        'claim_reward' => 'claim_reward',
        default => 'validate_activity_window',
    };

    return buildFlowWithType($activityId, $nodeType, ['lane' => $lane], $nextRunAt);
}

/**
 * @return ActivityFlow
 */
function buildFlowWithType(string $activityId, string $nodeType, array $payload, int $nextRunAt = 0): ActivityFlow
{
    $catalog = ActivityCatalogItem::fromArray([
        'id' => $activityId,
        'activity_id' => $activityId,
        'title' => $activityId,
        'update_time' => '2026-04-02T08:00:00+00:00',
    ]);
    $flow = ActivityFlowFactory::create($catalog, '2026-04-02', [
        new ActivityNode($nodeType, $payload),
    ]);

    if ($nextRunAt <= 0) {
        return $flow;
    }

    $row = $flow->toArray();
    $row['next_run_at'] = $nextRunAt;

    return ActivityFlow::fromArray($row);
}

function findNodeIndexByType(ActivityFlow $flow, string $nodeType): int
{
    foreach ($flow->nodes() as $index => $node) {
        if ($node->type() === $nodeType) {
            return (int)$index;
        }
    }

    return -1;
}

function moveFlowToNodeIndex(ActivityFlow $flow, int $nodeIndex): ActivityFlow
{
    $row = $flow->toArray();
    $row['current_node_index'] = $nodeIndex;

    return ActivityFlow::fromArray($row);
}
