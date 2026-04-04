<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowFactory;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\EraTaskGateway;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\WatchLiveGateway;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\WatchVideoGateway;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraClaimRewardNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraFollowNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraShareNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraUnfollowNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraWatchLiveNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Node\EraWatchVideoNodeRunner;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraTaskCapabilityResolver;
use Tests\Support\Assert;

$followRunnerSource = file_get_contents(__DIR__ . '/../../plugin/ActivityLottery/Internal/Node/EraFollowNodeRunner.php');
Assert::true(is_string($followRunnerSource), '应能读取 EraFollowNodeRunner 源码。');
Assert::true(
    str_contains($followRunnerSource, 'use Bhp\\Api\\Api\\X\\Relation\\ApiRelation;'),
    'EraFollowNodeRunner 应引用 X\\Relation\\ApiRelation。',
);

$now = time();
$flow = buildEraTaskFlow();

$shareEvents = [];
$shareRunner = new EraShareNodeRunner(
    static function (string $taskId, string $counter, string $url) use (&$shareEvents): array {
        $shareEvents[] = [
            'task_id' => $taskId,
            'counter' => $counter,
            'url' => $url,
        ];

        return ['code' => 0, 'message' => 'ok'];
    },
);
$shareResult = $shareRunner->run(
    $flow,
    new ActivityNode('era_task_share', ['lane' => 'task_status', 'task_id' => 'task-share']),
    $now,
);
Assert::true($shareResult->ok(), '分享节点应可执行。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($shareResult->payload()['node_status'] ?? ''), '分享节点应返回 succeeded。');
Assert::same('task-share', (string)($shareEvents[0]['task_id'] ?? ''), '分享节点应上报对应的 task_id。');

$followEvents = [];
$followRunner = new EraFollowNodeRunner(
    static function (int $uid) use (&$followEvents): array {
        $followEvents[] = $uid;
        return ['code' => 0, 'message' => 'ok'];
    },
);
$firstFollowResult = $followRunner->run(
    $flow,
    new ActivityNode('era_task_follow', ['lane' => 'follow', 'task_id' => 'task-follow']),
    $now,
);
Assert::true($firstFollowResult->ok(), '关注节点首步应可执行。');
Assert::same(ActivityNodeStatus::WAITING, (string)($firstFollowResult->payload()['node_status'] ?? ''), '关注多个目标时首步应返回 waiting。');
Assert::same([10001], $followEvents, '关注节点首步应只处理一个 UID。');
Assert::same(1, (int)(readTaskRuntimePatch($firstFollowResult, 'task-follow')['follow_target_index'] ?? 0), '关注节点应推进 follow_target_index。');
Assert::same(['10001'], readTaskRuntimePatch($firstFollowResult, 'task-follow')['temporary_follow_uids'] ?? [], '关注节点应记录临时关注 UID。');
Assert::true((int)($firstFollowResult->payload()['next_run_at'] ?? 0) > $now, '关注节点 waiting 时应写入 next_run_at。');

$followFlow = applyEraNodeContextPatchToFlow($flow, $firstFollowResult->payload());
$secondFollowResult = $followRunner->run(
    $followFlow,
    new ActivityNode('era_task_follow', ['lane' => 'follow', 'task_id' => 'task-follow']),
    $now + 20,
);
Assert::true($secondFollowResult->ok(), '关注节点第二步应可执行。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($secondFollowResult->payload()['node_status'] ?? ''), '最后一个关注目标完成后节点应返回 succeeded。');
Assert::same([10001, 10002], $followEvents, '关注节点应按顺序处理多个 UID。');

$completedFollowEvents = [];
$completedFollowFlow = buildEraTaskFlow([
    'task-follow' => [
        'task_status' => 3,
    ],
]);
$completedFollowResult = (new EraFollowNodeRunner(
    static function (int $uid) use (&$completedFollowEvents): array {
        $completedFollowEvents[] = $uid;
        return ['code' => 0, 'message' => 'ok'];
    },
))->run(
    $completedFollowFlow,
    new ActivityNode('era_task_follow', ['lane' => 'follow', 'task_id' => 'task-follow']),
    $now,
);
Assert::true($completedFollowResult->ok(), '已完成的关注任务不应整体失败。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($completedFollowResult->payload()['node_status'] ?? ''), '已完成的关注任务应直接返回 succeeded。');
Assert::same(0, count($completedFollowEvents), '已完成的关注任务不应再次触发关注动作。');

$unfollowEvents = [];
$unfollowRunner = new EraUnfollowNodeRunner(
    static function (int $uid) use (&$unfollowEvents): array {
        $unfollowEvents[] = $uid;
        return ['code' => 0, 'message' => 'ok'];
    },
);
$unfollowFlow = applyEraNodeContextPatchToFlow($flow, [
    'context_patch' => [
        'era_task_runtime' => [
            'task-follow' => [
                'temporary_follow_uids' => ['10001', '10002'],
            ],
        ],
    ],
]);
$firstUnfollowResult = $unfollowRunner->run(
    $unfollowFlow,
    new ActivityNode('era_task_unfollow', ['lane' => 'unfollow', 'cleanup_scope' => 'temporary_follow_uids']),
    $now,
);
Assert::true($firstUnfollowResult->ok(), '尾部取消关注节点首步应可执行。');
Assert::same(ActivityNodeStatus::WAITING, (string)($firstUnfollowResult->payload()['node_status'] ?? ''), '多个待取消关注目标时首步应返回 waiting。');
Assert::same([10001], $unfollowEvents, '尾部取消关注节点首步应只处理一个 UID。');

$unfollowFlow = applyEraNodeContextPatchToFlow($unfollowFlow, $firstUnfollowResult->payload());
$secondUnfollowResult = $unfollowRunner->run(
    $unfollowFlow,
    new ActivityNode('era_task_unfollow', ['lane' => 'unfollow', 'cleanup_scope' => 'temporary_follow_uids']),
    $now + 20,
);
Assert::true($secondUnfollowResult->ok(), '尾部取消关注节点第二步应可执行。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($secondUnfollowResult->payload()['node_status'] ?? ''), '最后一个待取消关注目标完成后节点应返回 succeeded。');
Assert::same([10001, 10002], $unfollowEvents, '尾部取消关注节点应按顺序处理多个 UID。');
Assert::same([], readTaskRuntimePatch($secondUnfollowResult, 'task-follow')['temporary_follow_uids'] ?? [], '尾部取消关注完成后应清空 temporary_follow_uids。');

$claimEvents = [];
$claimRunner = new EraClaimRewardNodeRunner(new EraTaskGateway(
    static function (string $taskId): array {
        if ($taskId === 'task-claim-bind') {
            return ['code' => 0, 'data' => ['status' => 1], 'message' => 'ok'];
        }

        return [
            'code' => 0,
            'data' => [
                'status' => 2,
                'act_id' => 'act-1',
                'act_name' => '活动 1',
                'task_name' => '领取奖励',
                'reward_info' => ['award_name' => '测试奖品'],
            ],
            'message' => 'ok',
        ];
    },
    static function (string $taskId, array $payload) use (&$claimEvents): array {
        $claimEvents[] = [
            'task_id' => $taskId,
            'payload' => $payload,
        ];

        return ['code' => 0, 'message' => 'ok'];
    },
));
$claimResult = $claimRunner->run(
    $flow,
    new ActivityNode('era_task_claim_reward', ['lane' => 'claim_reward', 'task_id' => 'task-claim']),
    $now,
);
Assert::true($claimResult->ok(), '领奖节点应可执行。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($claimResult->payload()['node_status'] ?? ''), '领奖成功后节点应返回 succeeded。');
Assert::same('task-claim', (string)($claimEvents[0]['task_id'] ?? ''), '领奖节点应调用 receiveReward。');

$claimBindResult = $claimRunner->run(
    $flow,
    new ActivityNode('era_task_claim_reward', ['lane' => 'claim_reward', 'task_id' => 'task-claim-bind']),
    $now,
);
Assert::true($claimBindResult->ok(), '需要绑定的领奖节点不应整体失败。');
Assert::same(ActivityNodeStatus::SKIPPED, (string)($claimBindResult->payload()['node_status'] ?? ''), '需要额外绑定时领奖节点应返回 skipped。');

$videoStartEvents = [];
$videoFinishEvents = [];
$videoRunner = new EraWatchVideoNodeRunner(
    'era_task_watch_video_fixed',
    new WatchVideoGateway(
        static fn (array $archive): ?array => $archive,
        static fn (string $topicId, int $limit = 20): array => [],
        static function (array $archive, string $sessionId) use (&$videoStartEvents): bool {
            $videoStartEvents[] = ['archive' => $archive, 'session_id' => $sessionId];
            return true;
        },
        static function (array $archive, int $watchedSeconds, string $sessionId) use (&$videoFinishEvents): bool {
            $videoFinishEvents[] = [
                'archive' => $archive,
                'watched_seconds' => $watchedSeconds,
                'session_id' => $sessionId,
            ];
            return true;
        },
    ),
);
$firstVideoResult = $videoRunner->run(
    $flow,
    new ActivityNode('era_task_watch_video_fixed', ['lane' => 'task_status', 'task_id' => 'task-video-fixed']),
    $now,
);
Assert::true($firstVideoResult->ok(), '视频节点首步应可执行。');
Assert::same(ActivityNodeStatus::WAITING, (string)($firstVideoResult->payload()['node_status'] ?? ''), '视频节点启动后应返回 waiting。');
$videoRuntime = readTaskRuntimePatch($firstVideoResult, 'task-video-fixed');
Assert::true(trim((string)($videoRuntime['watch_video_session'] ?? '')) !== '', '视频节点应写入 watch_video_session。');
Assert::true((int)($firstVideoResult->payload()['next_run_at'] ?? 0) > $now, '视频节点启动后应写入 next_run_at。');

$videoFlow = applyEraNodeContextPatchToFlow($flow, $firstVideoResult->payload());
$secondVideoResult = $videoRunner->run(
    $videoFlow,
    new ActivityNode('era_task_watch_video_fixed', ['lane' => 'watch_video', 'task_id' => 'task-video-fixed']),
    (int)($firstVideoResult->payload()['next_run_at'] ?? ($now + 45)),
);
Assert::true($secondVideoResult->ok(), '视频节点收尾阶段应可执行。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($secondVideoResult->payload()['node_status'] ?? ''), '达到观看目标后视频节点应返回 succeeded。');
Assert::true(($videoFinishEvents[0]['watched_seconds'] ?? 0) >= 30, '视频节点收尾时应带上累计观看秒数。');

$topicVideoRunner = new EraWatchVideoNodeRunner(
    'era_task_watch_video_topic',
    new WatchVideoGateway(
        static fn (array $archive): ?array => array_replace(['cid' => '8899', 'duration' => 50], $archive),
        static function (string $topicId, int $limit = 20): array {
            return [
                ['aid' => '9001', 'bvid' => 'BV1Topic9001', 'title' => '话题稿件 1'],
            ];
        },
        static fn (array $archive, string $sessionId): bool => true,
        static fn (array $archive, int $watchedSeconds, string $sessionId): bool => true,
    ),
);
$topicVideoResult = $topicVideoRunner->run(
    $flow,
    new ActivityNode('era_task_watch_video_topic', ['lane' => 'task_status', 'task_id' => 'task-video-topic']),
    $now,
);
Assert::true($topicVideoResult->ok(), '话题视频节点首步应可执行。');
Assert::same(ActivityNodeStatus::WAITING, (string)($topicVideoResult->payload()['node_status'] ?? ''), '话题视频节点启动后应返回 waiting。');
Assert::same(1, count(readTaskRuntimePatch($topicVideoResult, 'task-video-topic')['topic_archives'] ?? []), '话题视频节点应缓存 topic_archives。');

$liveHeartbeatEvents = [];
$liveRunner = new EraWatchLiveNodeRunner(new WatchLiveGateway(
    static fn (array $roomIds, int $areaId = 0, int $parentAreaId = 0): ?array => [
        'room_id' => 2233,
        'ruid' => 5566,
        'parent_area_id' => 9,
        'area_id' => 99,
        'heartbeat_interval' => 30,
        'ets' => 123456,
        'secret_key' => 'secret',
        'secret_rule' => [0, 1, 2],
        'last_heartbeat_at' => (float)$now,
        'live_buvid' => 'buvid',
        'live_uuid' => 'uuid',
    ],
    static function (array $session) use (&$liveHeartbeatEvents): array {
        $liveHeartbeatEvents[] = $session;
        return array_replace($session, [
            'seq_id' => (int)($session['seq_id'] ?? 0) + 1,
            'heartbeat_interval' => 30,
            'last_heartbeat_at' => (float)($session['last_heartbeat_at'] ?? microtime(true)) + 30.0,
            '_debug_elapsed_seconds' => 60,
        ]);
    },
));
$firstLiveResult = $liveRunner->run(
    $flow,
    new ActivityNode('era_task_watch_live', ['lane' => 'task_status', 'task_id' => 'task-live']),
    $now,
);
Assert::true($firstLiveResult->ok(), '直播节点首步应可执行。');
Assert::same(ActivityNodeStatus::WAITING, (string)($firstLiveResult->payload()['node_status'] ?? ''), '直播节点初始化后应返回 waiting。');
Assert::true(is_array(readTaskRuntimePatch($firstLiveResult, 'task-live')['live_session'] ?? null), '直播节点应写入 live_session。');

$liveFlow = applyEraNodeContextPatchToFlow($flow, $firstLiveResult->payload());
$secondLiveResult = $liveRunner->run(
    $liveFlow,
    new ActivityNode('era_task_watch_live', ['lane' => 'watch_live', 'task_id' => 'task-live']),
    $now + 30,
);
Assert::true($secondLiveResult->ok(), '直播节点心跳阶段应可执行。');
Assert::same(ActivityNodeStatus::SUCCEEDED, (string)($secondLiveResult->payload()['node_status'] ?? ''), '达到直播观看阈值后节点应返回 succeeded。');
Assert::same(1, count($liveHeartbeatEvents), '直播节点第二步应发送一次心跳。');

/**
 * @return array<string, mixed>
 */
function readTaskRuntimePatch(object $result, string $taskId): array
{
    $payload = method_exists($result, 'payload') ? $result->payload() : [];
    if (!is_array($payload)) {
        return [];
    }

    $runtime = $payload['context_patch']['era_task_runtime'] ?? [];
    if (!is_array($runtime)) {
        return [];
    }

    $taskRuntime = $runtime[$taskId] ?? [];
    return is_array($taskRuntime) ? $taskRuntime : [];
}

function buildEraTaskFlow(array $taskOverrides = []): ActivityFlow
{
    $catalog = ActivityCatalogItem::fromArray([
        'id' => 'era-node-flow',
        'activity_id' => 'era-node-activity',
        'lottery_id' => 'era-node-lottery',
        'title' => 'ERA 节点测试活动',
        'url' => 'https://www.bilibili.com/blackboard/era/test.html',
        'start_time' => 0,
        'end_time' => 0,
    ]);

    $flow = ActivityFlowFactory::create($catalog, '2026-04-03', [
        new ActivityNode('era_task_share', ['lane' => 'task_status', 'task_id' => 'task-share']),
    ]);

    $row = $flow->toArray();
    $tasks = [
        [
            'task_id' => 'task-share',
            'task_name' => '分享活动',
            'capability' => EraTaskCapabilityResolver::CAPABILITY_SHARE,
            'support_level' => EraTaskCapabilityResolver::SUPPORT_NOW,
            'counter' => '1/1',
            'task_status' => 1,
            'task_award_type' => 0,
        ],
        [
            'task_id' => 'task-follow',
            'task_name' => '关注 UP 主',
            'capability' => EraTaskCapabilityResolver::CAPABILITY_FOLLOW,
            'support_level' => EraTaskCapabilityResolver::SUPPORT_NOW,
            'target_uids' => ['10001', '10002'],
            'task_status' => 1,
            'task_award_type' => 0,
        ],
        [
            'task_id' => 'task-claim',
            'task_name' => '领取奖励',
            'capability' => EraTaskCapabilityResolver::CAPABILITY_CLAIM_REWARD,
            'support_level' => EraTaskCapabilityResolver::SUPPORT_NOW,
            'award_name' => '测试奖品',
            'task_status' => 2,
            'task_award_type' => 1,
        ],
        [
            'task_id' => 'task-claim-bind',
            'task_name' => '领取奖励(需绑定)',
            'capability' => EraTaskCapabilityResolver::CAPABILITY_CLAIM_REWARD,
            'support_level' => EraTaskCapabilityResolver::SUPPORT_NOW,
            'award_name' => '绑定奖品',
            'task_status' => 2,
            'task_award_type' => 1,
        ],
        [
            'task_id' => 'task-video-fixed',
            'task_name' => '观看视频 30 秒',
            'capability' => EraTaskCapabilityResolver::CAPABILITY_WATCH_VIDEO_FIXED,
            'support_level' => EraTaskCapabilityResolver::SUPPORT_NOW,
            'required_watch_seconds' => 30,
            'target_archives' => [
                ['aid' => '123456', 'cid' => '654321', 'duration' => 90, 'bvid' => 'BV1FixedDemo', 'title' => '固定稿件'],
            ],
            'task_status' => 1,
            'task_award_type' => 0,
        ],
        [
            'task_id' => 'task-video-topic',
            'task_name' => '观看话题视频 30 秒',
            'capability' => EraTaskCapabilityResolver::CAPABILITY_WATCH_VIDEO_TOPIC,
            'support_level' => EraTaskCapabilityResolver::SUPPORT_NOW,
            'topic_id' => 'topic-3001',
            'required_watch_seconds' => 30,
            'task_status' => 1,
            'task_award_type' => 0,
        ],
        [
            'task_id' => 'task-live',
            'task_name' => '观看直播 30 秒',
            'capability' => EraTaskCapabilityResolver::CAPABILITY_WATCH_LIVE,
            'support_level' => EraTaskCapabilityResolver::SUPPORT_NOW,
            'required_watch_seconds' => 30,
            'target_room_ids' => ['2233'],
            'target_area_id' => 99,
            'target_parent_area_id' => 9,
            'task_status' => 1,
            'task_award_type' => 0,
        ],
    ];
    foreach ($tasks as $index => $task) {
        $taskId = (string)($task['task_id'] ?? '');
        if ($taskId === '' || !isset($taskOverrides[$taskId]) || !is_array($taskOverrides[$taskId])) {
            continue;
        }

        $tasks[$index] = array_replace($task, $taskOverrides[$taskId]);
    }

    $row['context'] = [
        'era_page_snapshot' => [
            'activity_id' => 'era-node-activity',
            'page_id' => 'era-node-page',
            'lottery_id' => 'era-node-lottery',
            'start_time' => 0,
            'end_time' => 0,
            'tasks' => $tasks,
        ],
    ];

    return ActivityFlow::fromArray($row);
}

/**
 * @param array<string, mixed> $nodeResultPayload
 */
function applyEraNodeContextPatchToFlow(ActivityFlow $flow, array $nodeResultPayload): ActivityFlow
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
