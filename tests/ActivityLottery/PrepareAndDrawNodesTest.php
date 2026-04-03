<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Tests\Support\Assert;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowFactory;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\ActivityLotteryGateway;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\DrawGateway;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\EraTaskGateway;
use Bhp\Plugin\ActivityLottery\Internal\Node\ExecuteDrawNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\FinalizeFlowNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\LoadActivitySnapshotNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\NotifyDrawResultNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\ParseEraPageNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\RecordDrawResultNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\RefreshDrawTimesNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\ValidateActivityNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageParser;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageSnapshot;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraTaskCapabilityResolver;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraTaskSnapshot;

$task = EraTaskSnapshot::fromArray([
    'task_id' => 'task-share',
    'task_name' => '分享活动',
    'capability' => 'share',
    'task_status' => 1,
    'task_award_type' => 0,
]);
Assert::same('task-share', $task->taskId(), '任务快照应保留 task_id。');
Assert::same('share', $task->capability(), '任务快照应保留 capability。');
Assert::same(1, $task->taskStatus(), '任务快照应保留 task_status。');

$pageSnapshot = EraPageSnapshot::fromArray([
    'title' => 'ERA 活动页',
    'page_id' => 'page-1',
    'activity_id' => 'act-1',
    'lottery_id' => 'draw-1',
    'start_time' => 0,
    'end_time' => 0,
    'tasks' => [
        [
            'task_id' => 'task-share',
            'task_name' => '分享活动',
            'capability' => 'share',
            'task_status' => 1,
            'task_award_type' => 0,
        ],
        [
            'task_id' => 'task-claim',
            'task_name' => '领取奖励',
            'capability' => 'claim_reward',
            'task_status' => 2,
            'task_award_type' => 1,
        ],
    ],
]);
Assert::same(2, count($pageSnapshot->tasks()), '页面快照应保留任务列表。');
Assert::same('act-1', $pageSnapshot->activityId(), '页面快照应保留 activity_id。');

$resolver = new EraTaskCapabilityResolver();
Assert::same('share', $resolver->resolve([
    'task_name' => '分享活动',
    'btn_behavior' => [],
    'task_status' => 1,
    'task_award_type' => 0,
]), '能力解析器应识别分享任务。');
Assert::same('claim_reward', $resolver->resolve([
    'task_name' => '领取奖励',
    'btn_behavior' => [],
    'task_status' => 2,
    'task_award_type' => 1,
]), '能力解析器应识别可领奖任务。');
Assert::same('manual_only', $resolver->resolve([
    'task_name' => '投稿视频',
    'btn_behavior' => [],
    'task_status' => 1,
    'task_award_type' => 0,
]), '能力解析器应识别人工任务。');

$html = <<<'HTML'
<html>
<head><title>era-demo</title></head>
<body>
<script>
window.__initialState = {
  "EraTasklist": [{
    "tasklist": [
      {"taskId":"t-share","taskName":"分享活动","taskStatus":1,"taskAwardType":0,"btnBehavior":[]},
      {"taskId":"t-claim","taskName":"领取奖励","taskStatus":2,"taskAwardType":1,"btnBehavior":[]}
    ]
  }],
  "EraLottery": [{"config":{"activity_id":"activity-from-state","lottery_id":"lottery-from-state"}}]
};
window.__BILIACT_PAGEINFO__ = {"title":"测试活动","page_id":"page-from-state","activity_id":"activity-from-pageinfo"};
</script>
</body>
</html>
HTML;

$parser = new EraPageParser($resolver);
$parsedPage = $parser->parse($html);
Assert::true($parsedPage instanceof EraPageSnapshot, '页面解析器应返回 EraPageSnapshot。');
Assert::same('activity-from-state', $parsedPage->activityId(), '页面解析应优先取 initialState 的 activity_id。');
Assert::same('lottery-from-state', $parsedPage->lotteryId(), '页面解析应提取 lottery_id。');
Assert::same(2, count($parsedPage->tasks()), '页面解析应提取任务快照。');
Assert::same('claim_reward', $parsedPage->tasks()[1]->capability(), '页面解析任务应包含 capability。');

$notices = [];
$activityGateway = new ActivityLotteryGateway(
    static fn (string $url): string => $html,
    static function (string $channel, string $message) use (&$notices): void {
        $notices[] = ['channel' => $channel, 'message' => $message];
    },
);

$drawEvents = [];
$drawGateway = new DrawGateway(
    static function (array $payload) use (&$drawEvents): array {
        $drawEvents[] = ['type' => 'refresh', 'payload' => $payload];
        return ['code' => 0, 'data' => ['times' => 2], 'message' => 'ok'];
    },
    static function (array $payload) use (&$drawEvents): array {
        $drawEvents[] = ['type' => 'draw', 'payload' => $payload];

        static $counter = 0;
        $counter++;
        if ($counter === 1) {
            return ['code' => 0, 'data' => [['gift_id' => 0, 'gift_name' => '未中奖']], 'message' => 'ok'];
        }

        return ['code' => 0, 'data' => [['gift_id' => 1001, 'gift_name' => '测试奖品']], 'message' => 'ok'];
    },
);

$eraTaskGateway = new EraTaskGateway(
    static fn (string $taskId): array => ['code' => 0, 'data' => ['task_id' => $taskId], 'message' => 'ok'],
    static fn (string $taskId, array $payload): array => ['code' => 0, 'data' => ['task_id' => $taskId], 'message' => 'ok'],
);
$taskInfo = $eraTaskGateway->taskInfo('task-1');
Assert::same(0, (int)($taskInfo['code'] ?? -1), 'EraTaskGateway::taskInfo 应可调用。');
$taskReceive = $eraTaskGateway->receiveReward('task-1', ['reward' => 'x']);
Assert::same(0, (int)($taskReceive['code'] ?? -1), 'EraTaskGateway::receiveReward 应可调用。');

$flow = buildFlowWithActivity([
    'id' => 'prepare-draw-flow',
    'activity_id' => 'act-prepare-draw',
    'lottery_id' => 'lottery-prepare-draw',
    'title' => '准备抽奖测试',
    'url' => 'https://www.bilibili.com/blackboard/era/demo.html',
    'start_time' => 0,
    'end_time' => 0,
], 'load_activity_snapshot');

$loadRunner = new LoadActivitySnapshotNodeRunner($activityGateway);
$loadResult = $loadRunner->run($flow, new ActivityNode('load_activity_snapshot', ['lane' => 'page_fetch']), time());
Assert::true($loadResult->ok(), '加载活动快照节点应成功执行。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($loadResult->payload()['node_status'] ?? ''), '加载快照节点应返回 succeeded。');
Assert::true(trim((string)($loadResult->payload()['context_patch']['activity_snapshot']['html'] ?? '')) !== '', '加载快照节点应返回页面 HTML。');

$validateRunner = new ValidateActivityNodeRunner();
$validateResult = $validateRunner->run(
    $flow,
    new ActivityNode('validate_activity_window', ['lane' => 'task_status']),
    time()
);
Assert::true($validateResult->ok(), '活动校验节点在有效窗口应成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($validateResult->payload()['node_status'] ?? ''), '活动校验节点应返回 succeeded。');

$futureFlow = buildFlowWithActivity([
    'id' => 'future-flow',
    'activity_id' => 'act-future',
    'lottery_id' => 'lottery-future',
    'title' => '未开始活动',
    'url' => 'https://www.bilibili.com/blackboard/era/future.html',
    'start_time' => time() + 3600,
    'end_time' => 0,
], 'validate_activity_window');
$futureValidate = $validateRunner->run(
    $futureFlow,
    new ActivityNode('validate_activity_window', ['lane' => 'task_status']),
    time()
);
Assert::same(ActivityNodeStatus::WAITING, (string)($futureValidate->payload()['node_status'] ?? ''), '未开始活动应返回 waiting。');
Assert::true((int)($futureValidate->payload()['next_run_at'] ?? 0) > time(), '未开始活动应返回下一次执行时间。');

$parseRunner = new ParseEraPageNodeRunner($parser);
$parseNode = new ActivityNode('parse_era_page', [
    'lane' => 'page_fetch',
    'activity_snapshot' => [
        'html' => $html,
    ],
]);
$parseResult = $parseRunner->run($flow, $parseNode, time());
Assert::true($parseResult->ok(), '页面解析节点应执行成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($parseResult->payload()['node_status'] ?? ''), '页面解析节点应返回 succeeded。');
Assert::same(2, count((array)($parseResult->payload()['context_patch']['era_page_snapshot']['tasks'] ?? [])), '页面解析节点应返回任务快照。');

$refreshRunner = new RefreshDrawTimesNodeRunner($drawGateway);
$refreshResult = $refreshRunner->run($flow, new ActivityNode('refresh_draw_times', ['lane' => 'draw_refresh']), time());
Assert::true($refreshResult->ok(), '刷新抽奖次数节点应执行成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($refreshResult->payload()['node_status'] ?? ''), '刷新抽奖次数节点应返回 succeeded。');
Assert::same(2, (int)($refreshResult->payload()['context_patch']['draw_times_remaining'] ?? 0), '刷新抽奖次数节点应写入剩余次数。');

$executeRunner = new ExecuteDrawNodeRunner($drawGateway);
$firstDrawResult = $executeRunner->run($flow, new ActivityNode('execute_draw', [
    'lane' => 'draw_execute',
    'draw_times_remaining' => 2,
]), time());
Assert::true($firstDrawResult->ok(), '首次执行抽奖节点应成功。');
Assert::same(ActivityNodeStatus::WAITING, (string)($firstDrawResult->payload()['node_status'] ?? ''), '剩余次数大于 0 时抽奖节点应返回 waiting。');
Assert::same(1, (int)($firstDrawResult->payload()['context_patch']['draw_times_remaining'] ?? -1), '首次抽奖后剩余次数应减一。');

$secondDrawResult = $executeRunner->run($flow, new ActivityNode('execute_draw', [
    'lane' => 'draw_execute',
    'draw_times_remaining' => 1,
    'draw_results' => (array)($firstDrawResult->payload()['context_patch']['draw_results'] ?? []),
]), time());
Assert::true($secondDrawResult->ok(), '第二次执行抽奖节点应成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($secondDrawResult->payload()['node_status'] ?? ''), '最后一次抽奖后节点应返回 succeeded。');
Assert::same(0, (int)($secondDrawResult->payload()['context_patch']['draw_times_remaining'] ?? -1), '抽奖次数耗尽后应归零。');

$recordRunner = new RecordDrawResultNodeRunner();
$recordResult = $recordRunner->run($flow, new ActivityNode('record_draw_result', [
    'lane' => 'task_status',
    'draw_results' => (array)($secondDrawResult->payload()['context_patch']['draw_results'] ?? []),
]), time());
Assert::true($recordResult->ok(), '记录抽奖结果节点应执行成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($recordResult->payload()['node_status'] ?? ''), '记录抽奖结果节点应返回 succeeded。');
Assert::same(1, (int)($recordResult->payload()['context_patch']['draw_summary']['win_count'] ?? 0), '记录节点应统计中奖次数。');

$notifyRunner = new NotifyDrawResultNodeRunner($activityGateway);
$notifyResult = $notifyRunner->run($flow, new ActivityNode('notify_draw_result', [
    'lane' => 'task_status',
    'draw_summary' => (array)($recordResult->payload()['context_patch']['draw_summary'] ?? []),
]), time());
Assert::true($notifyResult->ok(), '通知节点应执行成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($notifyResult->payload()['node_status'] ?? ''), '通知节点应返回 succeeded。');
Assert::same(1, count($notices), '中奖时应触发一次通知。');
Assert::same('activity_lottery', (string)($notices[0]['channel'] ?? ''), '通知通道应为 activity_lottery。');

$finalizeRunner = new FinalizeFlowNodeRunner();
$finalizeResult = $finalizeRunner->run($flow, new ActivityNode('finalize_flow', ['lane' => 'task_status']), time());
Assert::true($finalizeResult->ok(), '收尾节点应执行成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($finalizeResult->payload()['node_status'] ?? ''), '收尾节点应返回 succeeded。');
Assert::same(ActivityFlowStatus::COMPLETED, (string)($finalizeResult->payload()['flow_status'] ?? ''), '收尾节点应建议 flow 进入 completed。');
Assert::same(1, count(array_values(array_filter($drawEvents, static fn (array $event): bool => $event['type'] === 'refresh'))), '应触发一次 refresh 请求。');
Assert::same(2, count(array_values(array_filter($drawEvents, static fn (array $event): bool => $event['type'] === 'draw'))), '应触发两次 draw 请求。');

/**
 * @param array<string, mixed> $activity
 */
function buildFlowWithActivity(array $activity, string $nodeType): ActivityFlow
{
    $catalog = ActivityCatalogItem::fromArray($activity);
    $flow = ActivityFlowFactory::create($catalog, '2026-04-03', [
        new ActivityNode($nodeType, ['lane' => 'task_status']),
    ]);

    $row = $flow->toArray();
    $row['activity'] = array_replace($row['activity'], $activity);

    return ActivityFlow::fromArray($row);
}

