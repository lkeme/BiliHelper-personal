<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowFactory;
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
$sameTickStartedAtMs = (int)(microtime(true) * 1000);
$sameTickBatch1 = $sameTickPool->pick([$sameTickFlowA, $sameTickFlowB, $sameTickFlowC], 100, $sameTickStartedAtMs);
Assert::same(2, count($sameTickBatch1), '同一 tick 第一轮应按预算选出 flow。');
$sameTickBatch2 = $sameTickPool->pick([$sameTickFlowA, $sameTickFlowB, $sameTickFlowC], 100, $sameTickStartedAtMs);
Assert::same(1, count($sameTickBatch2), '同一 tick 第二轮只应选出未被本 tick 选中过的 flow。');
Assert::same($sameTickFlowC->id(), $sameTickBatch2[0]->id(), '同一 tick 第二轮应选出剩余 flow。');
$sameTickBatch3 = $sameTickPool->pick([$sameTickFlowA, $sameTickFlowB, $sameTickFlowC], 100, $sameTickStartedAtMs);
Assert::same(0, count($sameTickBatch3), '同一 tick 内 flow 不应重复入选。');

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

/**
 * @return ActivityFlow
 */
function buildFlow(string $activityId, string $lane, int $nextRunAt = 0): ActivityFlow
{
    return buildFlowWithType($activityId, 'test_node', ['lane' => $lane], $nextRunAt);
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
