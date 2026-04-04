<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageSnapshot;

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

$nodeTypeContracts = ActivityFlowPlanner::nodeTypeContracts();
$fixedNodeTypes = [
    'load_activity_snapshot',
    'validate_activity_window',
    'parse_era_page',
    'refresh_draw_times',
    'execute_draw',
    'record_draw_result',
    'notify_draw_result',
    'final_claim_reward',
    'era_task_unfollow',
    'finalize_flow',
];
foreach ($fixedNodeTypes as $fixedNodeType) {
    Assert::true(
        isset($nodeTypeContracts[$fixedNodeType]['default_lane']),
        sprintf('node type=%s 契约应定义 default_lane。', $fixedNodeType),
    );
}
Assert::true(
    isset($nodeTypeContracts['era_task_claim_reward']),
    '动态领奖节点契约应使用 era_task_claim_reward。'
);
Assert::same(
    'claim_reward',
    (string)($nodeTypeContracts['era_task_claim_reward']['default_lane'] ?? ''),
    '动态领奖节点默认 lane 应为 claim_reward。'
);
Assert::same(
    'claim_reward',
    (string)($nodeTypeContracts['final_claim_reward']['default_lane'] ?? ''),
    '固定尾部领奖节点默认 lane 应为 claim_reward。'
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
    $refreshNodeIndex >= 0 && $refreshNodeIndex <= count($nodes) - 6,
    'refresh_draw_times 应位于抽奖阶段尾部起点之前。'
);

$dynamicSnapshot = EraPageSnapshot::fromArray([
    'title' => 'page',
    'page_id' => 'p-1',
    'activity_id' => 'act-flow-planner',
    'lottery_id' => 'l-1',
    'start_time' => 0,
    'end_time' => 0,
    'tasks' => [
        ['task_id' => 't-claim', 'task_name' => '领奖', 'capability' => 'claim_reward', 'task_status' => 2, 'task_award_type' => 1],
        ['task_id' => 't-follow', 'task_name' => '关注', 'capability' => 'follow', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 't-unfollow', 'task_name' => '取关', 'capability' => 'unfollow', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 't-watch-fixed', 'task_name' => '看视频', 'capability' => 'watch_video_fixed', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 't-watch-topic', 'task_name' => '看稿件', 'capability' => 'watch_video_topic', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 't-watch-live', 'task_name' => '看直播', 'capability' => 'watch_live', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 't-unknown', 'task_name' => '未知', 'capability' => 'unknown', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => '', 'task_name' => '空任务', 'capability' => 'watch_video_topic', 'task_status' => 1, 'task_award_type' => 0],
    ],
]);
$flowWithDynamic = $planner->plan($catalogItem, $dynamicSnapshot, '2026-04-02');
$dynamicNodes = array_values(array_filter(
    $flowWithDynamic->nodes(),
    static fn (ActivityNode $node): bool => str_starts_with($node->type(), 'era_task_')
        && !array_key_exists('cleanup_scope', $node->payload()),
));
$dynamicTypes = array_map(
    static fn (ActivityNode $node): string => $node->type(),
    $dynamicNodes,
);

Assert::true(
    in_array('era_task_claim_reward', $dynamicTypes, true),
    '动态领奖能力应映射为 era_task_claim_reward 节点。'
);
Assert::true(
    in_array('era_task_follow', $dynamicTypes, true),
    '动态 ERA 能力应映射为 era_task_follow 节点。'
);
Assert::true(
    in_array('era_task_unfollow', $dynamicTypes, true),
    '动态 ERA 能力应映射为 era_task_unfollow 节点。'
);
Assert::true(
    in_array('era_task_watch_video_fixed', $dynamicTypes, true),
    '动态 ERA 能力应映射为 era_task_watch_video_fixed 节点。'
);
Assert::true(
    in_array('era_task_watch_video_topic', $dynamicTypes, true),
    '动态 ERA 能力应映射为 era_task_watch_video_topic 节点。'
);
Assert::true(
    in_array('era_task_watch_live', $dynamicTypes, true),
    '动态 ERA 能力应映射为 era_task_watch_live 节点。'
);
Assert::true(
    in_array('era_task_skipped', $dynamicTypes, true),
    '不支持能力应生成 skipped 动态节点。'
);

$skippedNodes = array_values(array_filter(
    $dynamicNodes,
    static fn (ActivityNode $node): bool => $node->type() === 'era_task_skipped',
));
Assert::same(2, count($skippedNodes), '当前输入中 unknown 能力与空 task_id 都应生成 skipped 节点。');
$unsupportedSkippedNodes = array_values(array_filter(
    $skippedNodes,
    static fn (ActivityNode $node): bool => (string)($node->payload()['task_id'] ?? '') === 't-unknown',
));
Assert::same(1, count($unsupportedSkippedNodes), 'unknown 能力应生成携带 task_id 的 skipped 节点。');
Assert::same(
    ActivityNodeStatus::SKIPPED,
    $unsupportedSkippedNodes[0]->status(),
    '不支持能力生成的节点状态应为 skipped。'
);
Assert::same(
    'unknown',
    (string)($unsupportedSkippedNodes[0]->payload()['capability'] ?? ''),
    '不支持能力生成的 skipped 节点应保留 capability。'
);

$allNodeTypes = array_map(
    static fn (ActivityNode $node): string => $node->type(),
    $flowWithDynamic->nodes(),
);
Assert::true(
    in_array('final_claim_reward', $allNodeTypes, true),
    '固定尾部领奖节点应为 final_claim_reward。'
);
Assert::true(
    in_array('era_task_unfollow', $allNodeTypes, true),
    '固定尾部应包含临时关注回收节点。'
);
Assert::false(
    in_array('claim_reward', $allNodeTypes, true),
    '节点类型中不应再出现旧 claim_reward。'
);

$stableOrderSnapshotA = EraPageSnapshot::fromArray([
    'title' => 'stable-a',
    'page_id' => 'p-a',
    'activity_id' => 'act-flow-planner',
    'lottery_id' => 'l-a',
    'start_time' => 0,
    'end_time' => 0,
    'tasks' => [
        ['task_id' => 'a-live', 'task_name' => '看直播', 'capability' => 'watch_live', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-follow', 'task_name' => '关注', 'capability' => 'follow', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-share', 'task_name' => '分享', 'capability' => 'share', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-claim', 'task_name' => '领奖', 'capability' => 'claim_reward', 'task_status' => 2, 'task_award_type' => 1],
        ['task_id' => 'a-unsupported', 'task_name' => '手动', 'capability' => 'manual_only', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-video-topic', 'task_name' => '看稿件', 'capability' => 'watch_video_topic', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-video-fixed', 'task_name' => '看视频', 'capability' => 'watch_video_fixed', 'task_status' => 1, 'task_award_type' => 0],
    ],
]);
$stableOrderSnapshotB = EraPageSnapshot::fromArray([
    'title' => 'stable-b',
    'page_id' => 'p-b',
    'activity_id' => 'act-flow-planner',
    'lottery_id' => 'l-b',
    'start_time' => 0,
    'end_time' => 0,
    'tasks' => [
        ['task_id' => 'a-video-fixed', 'task_name' => '看视频', 'capability' => 'watch_video_fixed', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-unsupported', 'task_name' => '手动', 'capability' => 'manual_only', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-share', 'task_name' => '分享', 'capability' => 'share', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-live', 'task_name' => '看直播', 'capability' => 'watch_live', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-video-topic', 'task_name' => '看稿件', 'capability' => 'watch_video_topic', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-follow', 'task_name' => '关注', 'capability' => 'follow', 'task_status' => 1, 'task_award_type' => 0],
        ['task_id' => 'a-claim', 'task_name' => '领奖', 'capability' => 'claim_reward', 'task_status' => 2, 'task_award_type' => 1],
    ],
]);

$stableOrderFlowA = $planner->plan($catalogItem, $stableOrderSnapshotA, '2026-04-02');
$stableOrderFlowB = $planner->plan($catalogItem, $stableOrderSnapshotB, '2026-04-02');
$stableOrderDynamicA = array_values(array_filter(
    $stableOrderFlowA->nodes(),
    static fn (ActivityNode $node): bool => str_starts_with($node->type(), 'era_task_')
        && !array_key_exists('cleanup_scope', $node->payload()),
));
$stableOrderDynamicB = array_values(array_filter(
    $stableOrderFlowB->nodes(),
    static fn (ActivityNode $node): bool => str_starts_with($node->type(), 'era_task_')
        && !array_key_exists('cleanup_scope', $node->payload()),
));
$stableOrderTypeSequenceA = array_map(
    static fn (ActivityNode $node): string => $node->type(),
    $stableOrderDynamicA,
);
$stableOrderTypeSequenceB = array_map(
    static fn (ActivityNode $node): string => $node->type(),
    $stableOrderDynamicB,
);
Assert::same($stableOrderTypeSequenceA, $stableOrderTypeSequenceB, '同一任务集合乱序输入时，动态 ERA 节点顺序必须稳定。');
Assert::same(
    ['era_task_claim_reward', 'era_task_share', 'era_task_follow', 'era_task_watch_video_fixed', 'era_task_watch_video_topic', 'era_task_watch_live', 'era_task_skipped'],
    $stableOrderTypeSequenceA,
    '动态 ERA 节点应按 claim/share/follow/watch_video/watch_live/unsupported 的稳定优先级排序。',
);

