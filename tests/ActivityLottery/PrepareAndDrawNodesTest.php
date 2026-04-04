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
use Bhp\Plugin\ActivityLottery\Internal\Gateway\EraTaskProgressGateway;
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
    'support_level' => 'now',
    'counter' => '1/1',
    'jump_link' => 'https://example.com/topic?topic_id=2001',
    'topic_id' => '2001',
    'award_name' => '抽奖次数',
    'required_watch_seconds' => 120,
    'target_uids' => ['12345'],
    'target_video_ids' => ['BV1xx411c7mD'],
    'target_room_ids' => ['9999'],
    'target_area_id' => 55,
    'target_parent_area_id' => 9,
    'checkpoints' => [['watch_seconds' => 60]],
    'btn_behavior' => ['SHARE'],
    'task_status' => 1,
    'task_award_type' => 0,
]);
Assert::same('task-share', $task->taskId(), '任务快照应保留 task_id。');
Assert::same('share', $task->capability(), '任务快照应保留 capability。');
Assert::same('now', $task->supportLevel(), '任务快照应保留 support_level。');
Assert::same(1, $task->taskStatus(), '任务快照应保留 task_status。');
Assert::same('1/1', $task->counter(), '任务快照应保留 counter。');
Assert::same('https://example.com/topic?topic_id=2001', $task->jumpLink(), '任务快照应保留 jump_link。');
Assert::same('2001', $task->topicId(), '任务快照应保留 topic_id。');
Assert::same('抽奖次数', $task->awardName(), '任务快照应保留 award_name。');
Assert::same(120, $task->requiredWatchSeconds(), '任务快照应保留 required_watch_seconds。');
Assert::same(['12345'], $task->targetUids(), '任务快照应保留 target_uids。');
Assert::same(['BV1xx411c7mD'], $task->targetVideoIds(), '任务快照应保留 target_video_ids。');
Assert::same(['9999'], $task->targetRoomIds(), '任务快照应保留 target_room_ids。');
Assert::same(55, $task->targetAreaId(), '任务快照应保留 target_area_id。');
Assert::same(9, $task->targetParentAreaId(), '任务快照应保留 target_parent_area_id。');
Assert::same([['watch_seconds' => 60]], $task->checkpoints(), '任务快照应保留 checkpoints。');
Assert::same(['SHARE'], $task->btnBehavior(), '任务快照应保留 btn_behavior。');

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
Assert::same('later', $resolver->resolveSupportLevel([
    'task_name' => '关注主播',
    'btn_behavior' => [],
    'capability' => 'follow',
    'task_status' => 1,
    'task_award_type' => 0,
]), '缺少 follow 目标时 support_level 应为 later。');
Assert::same('manual', $resolver->resolveSupportLevel([
    'task_name' => '投稿视频',
    'btn_behavior' => [],
    'capability' => 'manual_only',
    'task_status' => 1,
    'task_award_type' => 0,
]), '人工任务 support_level 应为 manual。');

$html = <<<'HTML'
<html>
<head><title>era-demo</title></head>
<body>
<script>
window.__initialState = {
  "EraTasklist": [{
    "tasklist": [
      {"taskId":"t-share","taskName":"分享活动","taskStatus":1,"taskAwardType":0,"counter":"1/1","jumpLink":"https://www.bilibili.com/video/BV1xx411c7mD?topic_id=3001","topicID":"3001","awardName":"抽奖次数","checkpoints":[{"watch_seconds":30}],"btnBehavior":["SHARE"],"targetVideoIds":["BV1xx411c7mD"]},
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
Assert::same('now', $parsedPage->tasks()[0]->supportLevel(), '页面解析任务应包含 support_level。');
Assert::same('1/1', $parsedPage->tasks()[0]->counter(), '页面解析任务应保留 counter。');
Assert::same('https://www.bilibili.com/video/BV1xx411c7mD?topic_id=3001', $parsedPage->tasks()[0]->jumpLink(), '页面解析任务应保留 jump_link。');
Assert::same('3001', $parsedPage->tasks()[0]->topicId(), '页面解析任务应保留 topic_id。');
Assert::same('抽奖次数', $parsedPage->tasks()[0]->awardName(), '页面解析任务应保留 award_name。');
Assert::same([['watch_seconds' => 30]], $parsedPage->tasks()[0]->checkpoints(), '页面解析任务应保留 checkpoints。');
Assert::same(['SHARE'], $parsedPage->tasks()[0]->btnBehavior(), '页面解析任务应保留 btn_behavior。');

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

$expiredFlow = buildFlowWithActivity([
    'id' => 'expired-flow',
    'activity_id' => 'act-expired',
    'lottery_id' => 'lottery-expired',
    'title' => '已结束活动',
    'url' => 'https://www.bilibili.com/blackboard/era/expired.html',
    'start_time' => 0,
    'end_time' => time() - 3600,
], 'validate_activity_window');
$expiredValidate = $validateRunner->run(
    $expiredFlow,
    new ActivityNode('validate_activity_window', ['lane' => 'task_status']),
    time()
);
Assert::same(ActivityNodeStatus::SKIPPED, (string)($expiredValidate->payload()['node_status'] ?? ''), '已结束活动应返回 skipped。');
Assert::same(ActivityFlowStatus::EXPIRED, (string)($expiredValidate->payload()['flow_status'] ?? ''), '已结束活动应显式返回 flow_status=expired。');

$invalidStableFlow = buildFlowWithActivity([
    'id' => 'invalid-stable-flow',
    'activity_id' => 'act-for-invalid',
    'lottery_id' => 'lottery-for-invalid',
    'title' => '缺少稳定标识活动',
    'url' => 'https://www.bilibili.com/blackboard/era/invalid.html',
    'start_time' => 0,
    'end_time' => 0,
], 'validate_activity_window');
$invalidStableRow = $invalidStableFlow->toArray();
$invalidStableRow['activity'] = [
    'id' => 'invalid-stable-flow',
    'title' => '缺少稳定标识活动',
    'start_time' => 0,
    'end_time' => 0,
];
$invalidStableValidate = $validateRunner->run(
    ActivityFlow::fromArray($invalidStableRow),
    new ActivityNode('validate_activity_window', ['lane' => 'task_status']),
    time()
);
Assert::false($invalidStableValidate->ok(), '缺少稳定标识应校验失败。');
Assert::same(ActivityFlowStatus::FAILED, (string)($invalidStableValidate->payload()['flow_status'] ?? ''), '缺少稳定标识应显式返回 flow_status=failed。');

$emptyProgressGateway = new EraTaskProgressGateway(
    static function (array $taskIds, bool $needAllInvitedInfo = false): array {
        return [
            'code' => 0,
            'message' => '0',
            'data' => [
                'list' => [],
            ],
        ];
    },
);

$parseRunner = new ParseEraPageNodeRunner($parser, $emptyProgressGateway);
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

$progressParseRunner = new ParseEraPageNodeRunner(
    $parser,
    new EraTaskProgressGateway(
        static function (array $taskIds, bool $needAllInvitedInfo = false): array {
            return [
                'code' => 0,
                'message' => '0',
                'data' => [
                    'list' => [
                        [
                            'task_id' => 't-share',
                            'task_status' => 3,
                            'indicators' => [
                                ['cur_value' => 1, 'limit' => 1],
                            ],
                            'check_points' => [
                                ['list' => [['cur_value' => 1, 'limit' => 1]]],
                            ],
                        ],
                        [
                            'task_id' => 't-claim',
                            'task_status' => 2,
                            'indicators' => [
                                ['cur_value' => 0, 'limit' => 1],
                            ],
                            'check_points' => [
                                ['list' => [['cur_value' => 0, 'limit' => 1]]],
                            ],
                        ],
                    ],
                ],
            ];
        },
    ),
);
$progressParseResult = $progressParseRunner->run($flow, $parseNode, time());
Assert::true($progressParseResult->ok(), '带任务进度同步的页面解析节点应执行成功。');
Assert::same(2, count((array)($progressParseResult->payload()['context_patch']['era_task_progress_snapshot'] ?? [])), '页面解析节点应同步 totalv2 任务进度快照。');

$pageLevelTargetsHtml = <<<'HTML'
<html>
<body>
<script>
window.__initialState = {
  "H5FollowNew": [{
    "uid":"90001",
    "uname":"主播A",
    "addLotteryTimes":true,
    "followUidList":[{"uid":"90002","uname":"主播B"}]
  }],
  "PcSlidePlayer": [{
    "videoIds":"BV1ab411c7m1,BV2ab411c7m2",
    "videosDetail":[]
  }],
  "H5SlideVideos": [{
    "aids":"12345,67890",
    "videoList":[]
  }],
  "EraLiveNonRevenuePlayer": [{
    "roomsConfig":[{"roomId":"2233"}]
  }],
  "EraTasklist": [{
    "tasklist":[
      {"taskId":"t-follow-page","taskName":"关注主播A","taskStatus":1,"taskAwardType":0,"btnBehavior":[]},
      {"taskId":"t-video-page","taskName":"观看视频1分钟","taskStatus":1,"taskAwardType":0,"btnBehavior":[]},
      {"taskId":"t-live-page","taskName":"观看直播10秒","taskStatus":1,"taskAwardType":0,"btnBehavior":[]}
    ]
  }],
  "EraLottery": [{"config":{"activity_id":"activity-page-targets","lottery_id":"lottery-page-targets"}}]
};
window.__BILIACT_PAGEINFO__ = {"title":"页面目标测试","page_id":"page-targets"};
</script>
</body>
</html>
HTML;
$pageLevelParsed = $parser->parse($pageLevelTargetsHtml);
Assert::true($pageLevelParsed instanceof EraPageSnapshot, '页面级目标 HTML 应可被解析。');
$pageLevelFollowTask = findTaskById($pageLevelParsed, 't-follow-page');
Assert::same(['90001'], $pageLevelFollowTask->targetUids(), '页面级 H5FollowNew 目标应归并到任务 target_uids。');
Assert::same('now', $pageLevelFollowTask->supportLevel(), '页面级 follow 目标可执行时 support_level 应为 now。');
$pageLevelVideoTask = findTaskById($pageLevelParsed, 't-video-page');
Assert::true($pageLevelVideoTask->targetVideoIds() !== [], '页面级 PcSlidePlayer/H5SlideVideos 目标应归并到任务 target_video_ids。');
$pageLevelLiveTask = findTaskById($pageLevelParsed, 't-live-page');
Assert::same(['2233'], $pageLevelLiveTask->targetRoomIds(), '页面级 EraLiveNonRevenuePlayer 房间应归并到任务 target_room_ids。');
Assert::same('now', $pageLevelLiveTask->supportLevel(), '页面级直播目标可执行时 support_level 应为 now。');

$urlOnlyFlow = buildFlowWithActivity([
    'id' => 'url-only-flow',
    'title' => '仅 URL 活动',
    'url' => 'https://www.bilibili.com/blackboard/era/url-only.html',
], 'refresh_draw_times');
$urlOnlyFlow = applyContextPatchToFlow($urlOnlyFlow, [
    'context_patch' => [
        'era_page_snapshot' => [
            'activity_id' => 'act-from-context',
            'page_id' => 'page-from-context',
            'lottery_id' => 'lottery-from-context',
            'start_time' => 0,
            'end_time' => 0,
            'tasks' => [],
        ],
    ],
]);
$contextSidEvents = [];
$contextSidDrawGateway = new DrawGateway(
    static function (array $payload) use (&$contextSidEvents): array {
        $contextSidEvents[] = ['type' => 'refresh', 'payload' => $payload];
        return ['code' => 0, 'data' => ['times' => 1], 'message' => 'ok'];
    },
    static fn (array $payload): array => ['code' => 0, 'data' => [['gift_id' => 0, 'gift_name' => '未中奖']], 'message' => 'ok'],
);
$contextSidRefreshRunner = new RefreshDrawTimesNodeRunner($contextSidDrawGateway);
$contextSidRefreshResult = $contextSidRefreshRunner->run(
    $urlOnlyFlow,
    new ActivityNode('refresh_draw_times', ['lane' => 'draw_refresh']),
    time()
);
Assert::true($contextSidRefreshResult->ok(), 'lottery_id 仅来自 context 时刷新抽奖次数应成功。');
Assert::same(
    'lottery-from-context',
    (string)($contextSidEvents[0]['payload']['sid'] ?? ''),
    'lottery_id 缺失时应优先使用 era_page_snapshot.lottery_id 作为 sid。',
);

$validateFromContextFlow = buildFlowWithActivity([
    'id' => 'validate-from-context-flow',
    'title' => '时间窗来自 context',
    'url' => 'https://www.bilibili.com/blackboard/era/validate-context.html',
    'start_time' => 0,
    'end_time' => 0,
], 'validate_activity_window');
$futureStart = time() + 1800;
$validateFromContextWaitingFlow = applyContextPatchToFlow($validateFromContextFlow, [
    'context_patch' => [
        'era_page_snapshot' => [
            'activity_id' => 'ctx-act-waiting',
            'page_id' => 'ctx-page-waiting',
            'lottery_id' => 'ctx-lottery-waiting',
            'start_time' => $futureStart,
            'end_time' => 0,
            'tasks' => [],
        ],
    ],
]);
$validateFromContextWaiting = $validateRunner->run(
    $validateFromContextWaitingFlow,
    new ActivityNode('validate_activity_window', ['lane' => 'task_status']),
    time()
);
Assert::same(ActivityNodeStatus::WAITING, (string)($validateFromContextWaiting->payload()['node_status'] ?? ''), 'catalog 仅 URL 时，validate 应读取 context.start_time 并返回 waiting。');
Assert::same($futureStart, (int)($validateFromContextWaiting->payload()['next_run_at'] ?? 0), 'waiting 场景应将 next_run_at 设为 context.start_time。');

$validateFromContextExpiredFlow = applyContextPatchToFlow($validateFromContextFlow, [
    'context_patch' => [
        'era_page_snapshot' => [
            'activity_id' => 'ctx-act-expired',
            'page_id' => 'ctx-page-expired',
            'lottery_id' => 'ctx-lottery-expired',
            'start_time' => 0,
            'end_time' => time() - 1800,
            'tasks' => [],
        ],
    ],
]);
$validateFromContextExpired = $validateRunner->run(
    $validateFromContextExpiredFlow,
    new ActivityNode('validate_activity_window', ['lane' => 'task_status']),
    time()
);
Assert::same(ActivityNodeStatus::SKIPPED, (string)($validateFromContextExpired->payload()['node_status'] ?? ''), 'catalog 仅 URL 时，validate 应读取 context.end_time 并返回 skipped。');
Assert::same(ActivityFlowStatus::EXPIRED, (string)($validateFromContextExpired->payload()['flow_status'] ?? ''), 'context end_time 过期时应显式返回 flow_status=expired。');

$contextOnlyExecuteEvents = [];
$contextOnlyExecuteGateway = new DrawGateway(
    static fn (array $payload): array => ['code' => 0, 'data' => ['times' => 1], 'message' => 'ok'],
    static function (array $payload) use (&$contextOnlyExecuteEvents): array {
        $contextOnlyExecuteEvents[] = ['type' => 'draw', 'payload' => $payload];
        return ['code' => 0, 'data' => [['gift_id' => 0, 'gift_name' => '未中奖']], 'message' => 'ok'];
    },
);
$contextOnlyExecuteFlow = buildFlowWithActivity([
    'id' => 'context-only-execute-flow',
    'activity_id' => 'ctx-only-execute',
    'lottery_id' => 'ctx-only-lottery',
    'title' => '仅 context 传递抽奖状态',
    'url' => 'https://www.bilibili.com/blackboard/era/context-only-execute.html',
], 'execute_draw');
$contextOnlyExecuteRunner = new ExecuteDrawNodeRunner($contextOnlyExecuteGateway);
$contextOnlyExecuteResult = $contextOnlyExecuteRunner->run(
    $contextOnlyExecuteFlow,
    new ActivityNode('execute_draw', [
        'lane' => 'draw_execute',
        'draw_times_remaining' => 1,
        'draw_results' => [['gift_id' => 0, 'gift_name' => 'payload 注入']],
    ]),
    time()
);
Assert::same(ActivityNodeStatus::SKIPPED, (string)($contextOnlyExecuteResult->payload()['node_status'] ?? ''), 'execute_draw 应只消费 flow context，不应回退读取 node payload 里的抽奖状态。');
Assert::same(0, count($contextOnlyExecuteEvents), '未通过 context 写入次数时不应触发 draw 请求。');

$missingGiftNameEvents = [];
$missingGiftNameGateway = new DrawGateway(
    static fn (array $payload): array => ['code' => 0, 'data' => ['times' => 1], 'message' => 'ok'],
    static function (array $payload) use (&$missingGiftNameEvents): array {
        $missingGiftNameEvents[] = ['type' => 'draw', 'payload' => $payload];
        return ['code' => 0, 'data' => [['gift_id' => 0]], 'message' => 'ok'];
    },
);
$missingGiftNameFlow = applyContextPatchToFlow(buildFlowWithActivity([
    'id' => 'missing-gift-name-flow',
    'activity_id' => 'missing-gift-name-activity',
    'lottery_id' => 'missing-gift-name-lottery',
    'title' => '抽奖结果缺少奖品名',
    'url' => 'https://www.bilibili.com/blackboard/era/missing-gift-name.html',
], 'execute_draw'), [
    'context_patch' => [
        'draw_times_remaining' => 1,
        'draw_results' => [],
    ],
]);
$missingGiftNameRunner = new ExecuteDrawNodeRunner($missingGiftNameGateway);
$missingGiftNameResult = $missingGiftNameRunner->run(
    $missingGiftNameFlow,
    new ActivityNode('execute_draw', ['lane' => 'draw_execute']),
    time()
);
Assert::false($missingGiftNameResult->ok(), '抽奖返回缺少 gift_name 时 execute_draw 应失败。');
Assert::same(ActivityNodeStatus::FAILED, (string)($missingGiftNameResult->payload()['node_status'] ?? ''), '抽奖返回缺少 gift_name 时应返回 failed。');
Assert::true(str_contains($missingGiftNameResult->message(), '奖品名'), '抽奖返回缺少 gift_name 时应返回明确错误信息。');
Assert::same(1, count($missingGiftNameEvents), '缺少 gift_name 的抽奖响应也应被实际请求一次。');

$refreshRunner = new RefreshDrawTimesNodeRunner($drawGateway);
$refreshResult = $refreshRunner->run($flow, new ActivityNode('refresh_draw_times', ['lane' => 'draw_refresh']), time());
Assert::true($refreshResult->ok(), '刷新抽奖次数节点应执行成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($refreshResult->payload()['node_status'] ?? ''), '刷新抽奖次数节点应返回 succeeded。');
Assert::same(2, (int)($refreshResult->payload()['context_patch']['draw_times_remaining'] ?? 0), '刷新抽奖次数节点应写入剩余次数。');

$drawFlow = applyContextPatchToFlow($flow, $refreshResult->payload());
$executeRunner = new ExecuteDrawNodeRunner($drawGateway);
$firstDrawResult = $executeRunner->run($drawFlow, new ActivityNode('execute_draw', [
    'lane' => 'draw_execute',
]), time());
Assert::true($firstDrawResult->ok(), '首次执行抽奖节点应成功。');
Assert::same(ActivityNodeStatus::WAITING, (string)($firstDrawResult->payload()['node_status'] ?? ''), '剩余次数大于 0 时抽奖节点应返回 waiting。');
Assert::same(1, (int)($firstDrawResult->payload()['context_patch']['draw_times_remaining'] ?? -1), '首次抽奖后剩余次数应减一。');

$drawFlow = applyContextPatchToFlow($drawFlow, $firstDrawResult->payload());
$secondDrawResult = $executeRunner->run($drawFlow, new ActivityNode('execute_draw', [
    'lane' => 'draw_execute',
]), time());
Assert::true($secondDrawResult->ok(), '第二次执行抽奖节点应成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($secondDrawResult->payload()['node_status'] ?? ''), '最后一次抽奖后节点应返回 succeeded。');
Assert::same(0, (int)($secondDrawResult->payload()['context_patch']['draw_times_remaining'] ?? -1), '抽奖次数耗尽后应归零。');

$drawFlow = applyContextPatchToFlow($drawFlow, $secondDrawResult->payload());
$recordRunner = new RecordDrawResultNodeRunner();
$recordResult = $recordRunner->run($drawFlow, new ActivityNode('record_draw_result', [
    'lane' => 'task_status',
]), time());
Assert::true($recordResult->ok(), '记录抽奖结果节点应执行成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($recordResult->payload()['node_status'] ?? ''), '记录抽奖结果节点应返回 succeeded。');
Assert::same(1, (int)($recordResult->payload()['context_patch']['draw_summary']['win_count'] ?? 0), '记录节点应统计中奖次数。');

$drawFlow = applyContextPatchToFlow($drawFlow, $recordResult->payload());
$notifyRunner = new NotifyDrawResultNodeRunner($activityGateway);
$notifyResult = $notifyRunner->run($drawFlow, new ActivityNode('notify_draw_result', [
    'lane' => 'task_status',
]), time());
Assert::true($notifyResult->ok(), '通知节点应执行成功。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($notifyResult->payload()['node_status'] ?? ''), '通知节点应返回 succeeded。');
Assert::same(1, count($notices), '中奖时应触发一次通知。');
Assert::same('activity_lottery', (string)($notices[0]['channel'] ?? ''), '通知通道应为 activity_lottery。');

$expiredDrawEvents = [];
$expiredDrawGateway = new DrawGateway(
    static function (array $payload) use (&$expiredDrawEvents): array {
        $expiredDrawEvents[] = ['type' => 'refresh', 'payload' => $payload];
        return ['code' => 0, 'data' => ['times' => 1], 'message' => 'ok'];
    },
    static function (array $payload) use (&$expiredDrawEvents): array {
        $expiredDrawEvents[] = ['type' => 'draw', 'payload' => $payload];
        return ['code' => 0, 'data' => [['gift_id' => 0, 'gift_name' => '未中奖']], 'message' => 'ok'];
    },
);
$expiredRefreshRunner = new RefreshDrawTimesNodeRunner($expiredDrawGateway);
if ((string)($expiredValidate->payload()['flow_status'] ?? '') !== ActivityFlowStatus::EXPIRED) {
    $expiredRefreshRunner->run($expiredFlow, new ActivityNode('refresh_draw_times', ['lane' => 'draw_refresh']), time());
}
Assert::same(0, count($expiredDrawEvents), '已结束活动校验返回 expired 后，不应继续进入 draw 段。');

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

/**
 * @param array<string, mixed> $nodeResultPayload
 */
function applyContextPatchToFlow(ActivityFlow $flow, array $nodeResultPayload): ActivityFlow
{
    $patch = is_array($nodeResultPayload['context_patch'] ?? null)
        ? $nodeResultPayload['context_patch']
        : [];

    if ($patch === []) {
        return $flow;
    }

    $row = $flow->toArray();
    $row['context'] = array_replace(
        is_array($row['context'] ?? null) ? $row['context'] : [],
        $patch,
    );

    return ActivityFlow::fromArray($row);
}

function findTaskById(EraPageSnapshot $snapshot, string $taskId): EraTaskSnapshot
{
    foreach ($snapshot->tasks() as $task) {
        if ($task->taskId() === $taskId) {
            return $task;
        }
    }

    throw new RuntimeException('找不到任务: ' . $taskId);
}
