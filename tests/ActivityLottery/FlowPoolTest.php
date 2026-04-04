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
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageSnapshot;

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
$defaultTickStartedAtMs = (int)(microtime(true) * 1000);

$flowA = buildFlow('pool-a', 'task_status');
$flowB = buildFlow('pool-b', 'task_status');
$flowC = buildFlow('pool-c', 'task_status');

$cursorPick1 = $picker->pick([$flowA, $flowB, $flowC], 2);
Assert::same(2, count($cursorPick1), 'picker 首次选择应命中 limit。');
Assert::same(2, $picker->cursor(), 'picker 游标应在选择后推进。');
$picker->restoreCursor(1);
Assert::same(1, $picker->cursor(), 'picker 应可从外部恢复游标。');
$cursorPick2 = $picker->pick([$flowA, $flowB, $flowC], 2);
Assert::same($flowB->id(), $cursorPick2[0]->id(), '恢复游标后应从指定位置继续选择。');
Assert::same($flowC->id(), $cursorPick2[1]->id(), '恢复游标后后续选择顺序应可预测。');

$batch1 = $pool->pick([$flowA, $flowB, $flowC], 100, $defaultTickStartedAtMs);
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
$laneBatch = $pool->pick([$drawFlow, $statusFlow], 105, $defaultTickStartedAtMs);
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
$blockedFirstBatch = $blockedFirstPool->pick([$blockedFlow, $readyFlow1, $readyFlow2], 100, $defaultTickStartedAtMs);
Assert::same(2, count($blockedFirstBatch), 'budget=2 且首条 flow 被限速时，不应占用预算位。');
$pickedIds = array_map(static fn (ActivityFlow $flow): string => $flow->id(), $blockedFirstBatch);
Assert::false(in_array($blockedFlow->id(), $pickedIds, true), '被阻塞 flow 应被跳过，不得占预算位。');
Assert::true(in_array($readyFlow1->id(), $pickedIds, true), '后续 ready flow 应可入选。');
Assert::true(in_array($readyFlow2->id(), $pickedIds, true), '预算应留给后续 ready flow。');

$futureFlow = buildFlow('pool-future', 'task_status', 999);
$futureBatch = $pool->pick([$futureFlow], 100, $defaultTickStartedAtMs);
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
$sameTickStartedAtMs = $defaultTickStartedAtMs + 500;
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
    $unknownLanePool->pick([buildFlow('unknown-lane', 'unknown_lane')], 100, $defaultTickStartedAtMs);
} catch (\RuntimeException $e) {
    $unknownLaneThrown = str_contains($e->getMessage(), 'lane') && str_contains($e->getMessage(), '契约冲突');
}
Assert::true($unknownLaneThrown, '已知 node type 的冲突 lane 应触发异常，不应静默降级。');

$unknownNodeTypePool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$unknownNodeTypeFlow = buildFlowWithType('unknown-node-type', 'mystery_node', []);
$unknownNodeTypeThrown = false;
try {
    $unknownNodeTypePool->pick([$unknownNodeTypeFlow], 100, $defaultTickStartedAtMs);
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
    $unknownNodeTypeWithLanePool->pick([$unknownNodeTypeWithLaneFlow], 100, $defaultTickStartedAtMs);
} catch (\RuntimeException $e) {
    $unknownNodeTypeWithLaneThrown = str_contains($e->getMessage(), '未知 node type');
}
Assert::true($unknownNodeTypeWithLaneThrown, '未知 node type 即使显式携带 lane 也应触发异常。');

$planner = new ActivityFlowPlanner();
$nodeContracts = ActivityFlowPlanner::nodeTypeContracts();
$fixedContractFlow = $planner->plan(ActivityCatalogItem::fromArray([
    'id' => 'fixed-contract-check',
    'activity_id' => 'fixed-contract-check',
    'title' => 'fixed-contract-check',
    'update_time' => '2026-04-02T08:00:00+00:00',
]), null, '2026-04-02');
$fixedContractNodeTypes = ['load_activity_snapshot', 'validate_activity_window', 'parse_era_page', 'refresh_draw_times', 'execute_draw', 'record_draw_result', 'notify_draw_result', 'final_claim_reward', 'era_task_unfollow', 'finalize_flow'];
foreach ($fixedContractFlow->nodes() as $node) {
    if (!in_array($node->type(), $fixedContractNodeTypes, true)) {
        continue;
    }

    $contractLane = (string)($nodeContracts[$node->type()]['default_lane'] ?? '');
    Assert::same($contractLane, (string)($node->payload()['lane'] ?? ''), sprintf('固定节点 lane 应由契约导出: %s', $node->type()));
}

$plannerCatalog = ActivityCatalogItem::fromArray([
    'id' => 'planner-pool-integration',
    'activity_id' => 'planner-pool-integration',
    'title' => 'planner-pool-integration',
    'update_time' => '2026-04-02T08:00:00+00:00',
]);
$dynamicFlow = $planner->plan(
    $plannerCatalog,
    EraPageSnapshot::fromArray([
        'title' => 'dynamic-flow',
        'page_id' => 'dynamic-page',
        'activity_id' => 'planner-pool-integration',
        'lottery_id' => 'dynamic-lottery',
        'start_time' => 0,
        'end_time' => 0,
        'tasks' => [
            ['task_id' => 'follow-1', 'task_name' => '关注', 'capability' => 'follow', 'task_status' => 1, 'task_award_type' => 0],
        ],
    ]),
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
$dynamicBatch = $dynamicPool->pick([$readyFollowFlow], 100, $defaultTickStartedAtMs);
Assert::same(1, count($dynamicBatch), 'planner 产出的 era_task_follow 节点应可被 pool 正常选中。');
Assert::same($readyFollowFlow->id(), $dynamicBatch[0]->id(), '动态 follow flow 应成功进入调度结果。');

$unsupportedFlow = $planner->plan(
    $plannerCatalog,
    EraPageSnapshot::fromArray([
        'title' => 'unsupported-flow',
        'page_id' => 'unsupported-page',
        'activity_id' => 'planner-pool-integration',
        'lottery_id' => 'unsupported-lottery',
        'start_time' => 0,
        'end_time' => 0,
        'tasks' => [
            ['task_id' => 'unsupported-1', 'task_name' => '投币', 'capability' => 'coin_topic', 'task_status' => 1, 'task_award_type' => 0],
        ],
    ]),
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
$skippedBatch = $skippedPool->pick([$readySkippedFlow], 100, $defaultTickStartedAtMs);
Assert::same(1, count($skippedBatch), 'skipped 节点进入 flow 后应可被 pool 接受而非因映射不一致报错。');
Assert::same($readySkippedFlow->id(), $skippedBatch[0]->id(), '含 skipped 节点的 flow 应可完成调度选择。');

$missingTickThrown = false;
try {
    $pool->pick([$flowA], 100);
} catch (\ArgumentCountError) {
    $missingTickThrown = true;
}
Assert::true($missingTickThrown, 'pick 不传 tickStartedAtMs 时必须显式失败，避免 silent reset。');

$singlePickDedupPool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$singlePickFlow = buildFlow('single-pick-dedup', 'task_status');
$singlePickDedupBatch = $singlePickDedupPool->pick(
    [$singlePickFlow, $singlePickFlow],
    100,
    $defaultTickStartedAtMs + 1_000,
);
Assert::same(1, count($singlePickDedupBatch), '单次 pick 内重复 flow_id 只能入选一次。');
Assert::same($singlePickFlow->id(), $singlePickDedupBatch[0]->id(), '单次 pick 去重后应保留目标 flow。');

$executionAccountingPool = new ActivityFlowPool(
    new ActivityFlowBudget(5, 2, 100),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$executionTickStartedAtMs = $defaultTickStartedAtMs + 1_200;
Assert::true($executionAccountingPool->canContinue($executionTickStartedAtMs, $executionTickStartedAtMs + 1.0), '新 tick 在预算内应允许继续。');
$executionAccountingPool->noteStepExecuted($executionTickStartedAtMs, 'execution-a', 20.5);
Assert::true($executionAccountingPool->canContinue($executionTickStartedAtMs, $executionTickStartedAtMs + 2.0), '执行一步后在预算内应允许继续。');
$executionAccountingPool->noteStepExecuted($executionTickStartedAtMs, 'execution-a', 10.5);
Assert::false($executionAccountingPool->canContinue($executionTickStartedAtMs, $executionTickStartedAtMs + 3.0), 'step 预算耗尽后应停止继续。');

$runtimeBudgetPool = new ActivityFlowPool(
    new ActivityFlowBudget(5, 10, 30),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$runtimeTickStartedAtMs = $defaultTickStartedAtMs + 1_400;
$runtimeBudgetPool->noteStepExecuted($runtimeTickStartedAtMs, 'runtime-a', 12.0);
$runtimeBudgetPool->noteStepExecuted($runtimeTickStartedAtMs, 'runtime-a', 19.5);
Assert::false($runtimeBudgetPool->canContinue($runtimeTickStartedAtMs, $runtimeTickStartedAtMs + 5.0), 'runtime 预算耗尽后应停止继续。');

$invalidFlowInputPool = new ActivityFlowPool(
    new ActivityFlowBudget(2, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['task_status' => 0]),
);
$invalidFlowThrown = false;
try {
    $invalidFlowInputPool->pick([$flowA, 'not-a-flow'], 100, $defaultTickStartedAtMs + 1_900);
} catch (\InvalidArgumentException $e) {
    $invalidFlowThrown = str_contains($e->getMessage(), 'ActivityFlow');
}
Assert::true($invalidFlowThrown, 'pick 输入包含非法元素时必须 fail fast。');

$allowedDynamicLaneFlow = buildFlowWithType('allowed-dynamic-lane', 'era_task_watch_video_fixed', ['lane' => 'watch_video']);
$allowedDynamicLanePool = new ActivityFlowPool(
    new ActivityFlowBudget(1, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['watch_video' => 0, 'task_status' => 0]),
);
$allowedDynamicLaneBatch = $allowedDynamicLanePool->pick([$allowedDynamicLaneFlow], 100, $defaultTickStartedAtMs + 1_500);
Assert::same(1, count($allowedDynamicLaneBatch), 'node type 显式指定允许的 lane 应通过。');

$disallowedDynamicLaneFlow = buildFlowWithType('disallowed-dynamic-lane', 'era_task_watch_video_fixed', ['lane' => 'follow']);
$disallowedDynamicLanePool = new ActivityFlowPool(
    new ActivityFlowBudget(1, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['watch_video' => 0, 'task_status' => 0, 'follow' => 0]),
);
$disallowedDynamicLaneThrown = false;
try {
    $disallowedDynamicLanePool->pick([$disallowedDynamicLaneFlow], 100, $defaultTickStartedAtMs + 1_700);
} catch (\RuntimeException $e) {
    $disallowedDynamicLaneThrown = str_contains($e->getMessage(), 'lane') && str_contains($e->getMessage(), 'era_task_watch_video_fixed');
}
Assert::true($disallowedDynamicLaneThrown, 'node type 显式指定不允许的 lane 必须 fail fast。');

$conflictLaneFlow = buildFlowWithType('conflict-lane', 'execute_draw', ['lane' => 'task_status']);
$conflictLanePool = new ActivityFlowPool(
    new ActivityFlowBudget(1, 6, 3000),
    new ActivityFlowPicker(),
    new ActivityLaneLimiter(['draw_execute' => 0, 'task_status' => 0]),
);
$conflictLaneThrown = false;
try {
    $conflictLanePool->pick([$conflictLaneFlow], 100, $defaultTickStartedAtMs + 2_000);
} catch (\RuntimeException $e) {
    $conflictLaneThrown = str_contains($e->getMessage(), 'lane') && str_contains($e->getMessage(), 'execute_draw');
}
Assert::true($conflictLaneThrown, '已知 node type 的 lane 若与 payload 冲突，必须 fail fast。');

/**
 * @return ActivityFlow
 */
function buildFlow(string $activityId, string $lane, int $nextRunAt = 0): ActivityFlow
{
    $nodeType = match ($lane) {
        'draw_execute' => 'execute_draw',
        'draw_refresh' => 'refresh_draw_times',
        'claim_reward' => 'final_claim_reward',
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
