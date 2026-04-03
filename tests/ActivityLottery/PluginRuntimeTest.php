<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 2) . '/plugin/ActivityLottery/ActivityLottery.php';

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\CatalogSourceInterface;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowFactory;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStore;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Node\NodeRunnerInterface;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowBudget;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowPool;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryClock;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryRuntime;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryWindow;
use Bhp\Scheduler\TaskResult;
use Tests\Support\Assert;

if (!defined('PROFILE_CACHE_PATH')) {
    $cachePath = sys_get_temp_dir() . '/bilihelper-activity-runtime-cache-' . substr(md5((string)__FILE__), 0, 8);
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0777, true);
    }
    define('PROFILE_CACHE_PATH', $cachePath);
}

$now = strtotime('2026-04-03 08:00:00');
$scope = 'ActivityLotteryRuntimeTest_' . substr(sha1((string)$now . '|' . (string)microtime(true)), 0, 12);
$runtimeLogs = [];
$loader = new ActivityCatalogLoader([
    new class implements CatalogSourceInterface {
        public function priority(): int
        {
            return 100;
        }

        public function load(): array
        {
            return [
                ActivityCatalogItem::fromArray([
                    'id' => 'runtime-activity-1',
                    'activity_id' => 'runtime-activity-1',
                    'lottery_id' => 'runtime-lottery-1',
                    'title' => 'Runtime 活动 1',
                    'url' => 'https://www.bilibili.com/blackboard/era/runtime-1.html',
                    'update_time' => '2026-04-03 08:00:00',
                ]),
            ];
        }
    },
]);
$store = new ActivityFlowStore($scope);
$runtime = new ActivityLotteryRuntime(
    $loader,
    $store,
    [
        new class implements NodeRunnerInterface {
            public function type(): string
            {
                return 'load_activity_snapshot';
            }

            public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
            {
                return new ActivityNodeResult(true, '加载完成', [
                    'node_status' => ActivityNodeStatus::SUCCEEDED,
                    'context_patch' => [
                        'activity_snapshot' => [
                            'html' => '<html></html>',
                        ],
                    ],
                ], $now);
            }
        },
    ],
    new ActivityFlowPlanner(),
    new ActivityFlowPool(new ActivityFlowBudget(1, 1, 3000)),
    new ActivityLotteryClock(static fn (): int => $now),
    new ActivityLotteryWindow('06:00:00', '23:00:00'),
    '06:00:00',
    '23:00:00',
    static function (string $level, string $message, array $context = []) use (&$runtimeLogs): void {
        $runtimeLogs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    },
);

Assert::same('2026-04-03', $runtime->bizDate(), 'runtime 应暴露当前 bizDate。');
$tickResult = $runtime->tick();
Assert::true($tickResult instanceof TaskResult, 'runtime->tick() 应返回 TaskResult。');
Assert::true($tickResult->nextRunAfterSeconds !== null, 'runtime->tick() 应给出下一次调度间隔。');
$storedFlows = $store->load('2026-04-03');
Assert::same(1, count($storedFlows), 'runtime->tick() 应创建并保存当天 flow。');
Assert::same(1, $storedFlows[0]->currentNodeIndex(), 'runtime->tick() 推进一步后应推进 current_node_index。');
Assert::same(ActivityNodeStatus::SUCCEEDED, $storedFlows[0]->nodes()[0]->status(), '已执行节点应写回 succeeded。');
Assert::true(findRuntimeLog($runtimeLogs, 'tick.start') !== null, 'runtime->tick() 应记录 tick.start 日志。');
Assert::true(findRuntimeLog($runtimeLogs, 'catalog.loaded') !== null, 'runtime->tick() 应记录目录加载日志。');
Assert::same(1, (int)(findRuntimeLog($runtimeLogs, 'catalog.loaded')['context']['catalog_count'] ?? 0), '目录日志应包含 catalog_count。');
Assert::same('load_activity_snapshot', (string)(findRuntimeLog($runtimeLogs, 'node.execute')['context']['node_type'] ?? ''), '节点执行日志应包含当前 node_type。');
Assert::same(1, (int)(findRuntimeLog($runtimeLogs, 'tick.finish')['context']['picked_flow_count'] ?? 0), 'tick 结束日志应包含 picked_flow_count。');

$outsideRuntime = new ActivityLotteryRuntime(
    $loader,
    new ActivityFlowStore($scope . '_outside'),
    [],
    new ActivityFlowPlanner(),
    new ActivityFlowPool(new ActivityFlowBudget(1, 1, 3000)),
    new ActivityLotteryClock(static fn (): int => strtotime('2026-04-03 01:30:00')),
    new ActivityLotteryWindow('06:00:00', '23:00:00'),
    '06:00:00',
    '23:00:00',
    static function (string $level, string $message, array $context = []) use (&$runtimeLogs): void {
        $runtimeLogs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    },
);
$outsideResult = $outsideRuntime->tick();
Assert::true($outsideResult->nextRunAfterSeconds !== null, '窗口外 tick 应直接返回下次窗口调度时间。');
Assert::true(findRuntimeLog($runtimeLogs, 'tick.outside_window') !== null, '窗口外 tick 应记录 outside_window 日志。');

$plugin = new class($runtime) extends ActivityLottery {
    public function __construct(private readonly ActivityLotteryRuntime $runtimeInstance)
    {
    }

    protected function enabled(string $key, bool $default = false): bool
    {
        return true;
    }

    protected function runtime(): ActivityLotteryRuntime
    {
        return $this->runtimeInstance;
    }
};
$pluginResult = $plugin->runOnce();
Assert::true($pluginResult instanceof TaskResult, '插件 runOnce() 应返回 TaskResult。');
Assert::true($pluginResult->nextRunAfterSeconds !== null, '插件 runOnce() 应委托给 runtime()->tick()。');

$businessLogs = [];
$businessScope = $scope . '_business';
$businessStore = new ActivityFlowStore($businessScope);
$businessFlow = ActivityFlowFactory::create(
    ActivityCatalogItem::fromArray([
        'id' => 'business-activity',
        'activity_id' => 'business-activity',
        'lottery_id' => 'business-lottery',
        'title' => '业务日志活动',
        'url' => 'https://www.bilibili.com/blackboard/era/business.html',
        'update_time' => '2026-04-03 08:00:00',
    ]),
    '2026-04-03',
    [
        new ActivityNode('era_task_follow', ['lane' => 'follow', 'task_id' => 'task-follow']),
    ],
);
$businessRow = $businessFlow->toArray();
$businessRow['context'] = [
    'era_page_snapshot' => [
        'activity_id' => 'business-activity',
        'page_id' => 'business-page',
        'lottery_id' => 'business-lottery',
        'start_time' => 0,
        'end_time' => 0,
        'tasks' => [
            [
                'task_id' => 'task-follow',
                'task_name' => '关注测试UP主',
                'capability' => 'follow',
                'support_level' => 'now',
                'target_uids' => ['12345'],
                'task_status' => 1,
                'task_award_type' => 0,
            ],
        ],
    ],
];
$businessStore->save([ActivityFlow::fromArray($businessRow)]);
$businessRuntime = new ActivityLotteryRuntime(
    new ActivityCatalogLoader([]),
    $businessStore,
    [
        new class implements NodeRunnerInterface {
            public function type(): string
            {
                return 'era_task_follow';
            }

            public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
            {
                return new ActivityNodeResult(true, '关注任务执行完成', [
                    'node_status' => ActivityNodeStatus::SUCCEEDED,
                ], $now);
            }
        },
    ],
    new ActivityFlowPlanner(),
    new ActivityFlowPool(new ActivityFlowBudget(1, 1, 3000)),
    new ActivityLotteryClock(static fn (): int => $now),
    new ActivityLotteryWindow('06:00:00', '23:00:00'),
    '06:00:00',
    '23:00:00',
    static function (string $level, string $message, array $context = []) use (&$businessLogs): void {
        $businessLogs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    },
);
$businessRuntime->tick();
$businessExecuteLog = findRuntimeLog($businessLogs, 'node.execute');
Assert::true($businessExecuteLog !== null, '业务节点执行时应记录 node.execute 日志。');
Assert::same('业务日志活动', (string)($businessExecuteLog['context']['activity_title'] ?? ''), '业务日志应包含 activity_title。');
Assert::same('关注测试UP主', (string)($businessExecuteLog['context']['task_name'] ?? ''), '业务日志应包含 task_name。');
Assert::true(str_contains((string)$businessExecuteLog['message'], '业务日志活动'), '业务执行日志消息应包含活动标题。');
Assert::true(str_contains((string)$businessExecuteLog['message'], '关注测试UP主'), '业务执行日志消息应包含任务名。');
$businessResultLog = findRuntimeLog($businessLogs, 'node.result');
Assert::true($businessResultLog !== null, '业务节点结束时应记录 node.result 日志。');
Assert::true(str_contains((string)$businessResultLog['message'], '关注任务执行完成'), '业务结果日志消息应包含节点结果。');

$followWaitingLogs = [];
$followWaitingRuntime = buildBusinessRuntime(
    $scope . '_follow_waiting',
    $now,
    [
        'id' => 'follow-activity',
        'activity_id' => 'follow-activity',
        'lottery_id' => 'follow-lottery',
        'title' => '关注日志活动',
        'url' => 'https://www.bilibili.com/blackboard/era/follow.html',
        'update_time' => '2026-04-03 08:00:00',
    ],
    [
        new ActivityNode('era_task_follow', ['lane' => 'follow', 'task_id' => 'task-follow']),
    ],
    [
        [
            'task_id' => 'task-follow',
            'task_name' => '关注测试UP主',
            'capability' => 'follow',
            'support_level' => 'now',
            'target_uids' => ['12345', '67890'],
            'task_status' => 1,
            'task_award_type' => 0,
        ],
    ],
    [
        new class implements NodeRunnerInterface {
            public function type(): string
            {
                return 'era_task_follow';
            }

            public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
            {
                return new ActivityNodeResult(true, '关注任务已推进到下一目标', [
                    'node_status' => ActivityNodeStatus::WAITING,
                    'next_run_at' => $now + 15,
                    'context_patch' => [
                        'era_task_runtime' => [
                            'task-follow' => [
                                'follow_target_index' => 1,
                                'temporary_follow_uids' => ['12345'],
                            ],
                        ],
                    ],
                ], $now);
            }
        },
    ],
    $followWaitingLogs,
);
$followWaitingRuntime->tick();
$followWaitingLog = findRuntimeLog($followWaitingLogs, 'node.result');
Assert::true($followWaitingLog !== null, '关注 waiting 场景应记录 node.result 日志。');
Assert::true(str_contains((string)$followWaitingLog['message'], '已完成 1/2'), '关注 waiting 日志应包含已完成数量。');
Assert::true(str_contains((string)$followWaitingLog['message'], '67890'), '关注 waiting 日志应包含下一目标 UID。');
Assert::true(str_contains((string)$followWaitingLog['message'], '15 秒后继续'), '关注 waiting 日志应包含等待秒数。');

$videoWaitingLogs = [];
$videoWaitingRuntime = buildBusinessRuntime(
    $scope . '_video_waiting',
    $now,
    [
        'id' => 'video-activity',
        'activity_id' => 'video-activity',
        'lottery_id' => 'video-lottery',
        'title' => '视频日志活动',
        'url' => 'https://www.bilibili.com/blackboard/era/video.html',
        'update_time' => '2026-04-03 08:00:00',
    ],
    [
        new ActivityNode('era_task_watch_video_topic', ['lane' => 'task_status', 'task_id' => 'task-video']),
    ],
    [
        [
            'task_id' => 'task-video',
            'task_name' => '每日累计观看当期活动视频',
            'capability' => 'watch_video_topic',
            'support_level' => 'now',
            'required_watch_seconds' => 60,
            'topic_id' => 'topic-100',
            'task_status' => 1,
            'task_award_type' => 0,
        ],
    ],
    [
        new class implements NodeRunnerInterface {
            public function type(): string
            {
                return 'era_task_watch_video_topic';
            }

            public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
            {
                return new ActivityNodeResult(true, '视频观看继续推进', [
                    'node_status' => ActivityNodeStatus::WAITING,
                    'next_run_at' => $now + 5,
                    'context_patch' => [
                        'era_task_runtime' => [
                            'task-video' => [
                                'local_watch_seconds' => 35,
                                'topic_archives' => [
                                    ['aid' => '9001', 'bvid' => 'BV1Topic9001', 'title' => '话题稿件 1'],
                                ],
                                'topic_archive_index' => 0,
                            ],
                        ],
                    ],
                ], $now);
            }
        },
    ],
    $videoWaitingLogs,
);
$videoWaitingRuntime->tick();
$videoWaitingLog = findRuntimeLog($videoWaitingLogs, 'node.result');
Assert::true($videoWaitingLog !== null, '视频 waiting 场景应记录 node.result 日志。');
Assert::true(str_contains((string)$videoWaitingLog['message'], '35/60 秒'), '视频 waiting 日志应包含累计观看秒数。');
Assert::true(str_contains((string)$videoWaitingLog['message'], 'BV1Topic9001'), '视频 waiting 日志应包含当前稿件标识。');
Assert::true(str_contains((string)$videoWaitingLog['message'], '5 秒后继续'), '视频 waiting 日志应包含等待秒数。');

$liveWaitingLogs = [];
$liveWaitingRuntime = buildBusinessRuntime(
    $scope . '_live_waiting',
    $now,
    [
        'id' => 'live-activity',
        'activity_id' => 'live-activity',
        'lottery_id' => 'live-lottery',
        'title' => '直播日志活动',
        'url' => 'https://www.bilibili.com/blackboard/era/live.html',
        'update_time' => '2026-04-03 08:00:00',
    ],
    [
        new ActivityNode('era_task_watch_live', ['lane' => 'watch_live', 'task_id' => 'task-live']),
    ],
    [
        [
            'task_id' => 'task-live',
            'task_name' => '每日观看直播',
            'capability' => 'watch_live',
            'support_level' => 'now',
            'required_watch_seconds' => 240,
            'target_room_ids' => ['2233'],
            'task_status' => 1,
            'task_award_type' => 0,
        ],
    ],
    [
        new class implements NodeRunnerInterface {
            public function type(): string
            {
                return 'era_task_watch_live';
            }

            public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
            {
                return new ActivityNodeResult(true, '直播观看继续推进', [
                    'node_status' => ActivityNodeStatus::WAITING,
                    'next_run_at' => $now + 30,
                    'context_patch' => [
                        'era_task_runtime' => [
                            'task-live' => [
                                'local_watch_seconds' => 120,
                                'live_session' => [
                                    'room_id' => 2233,
                                    'heartbeat_interval' => 30,
                                ],
                            ],
                        ],
                    ],
                ], $now);
            }
        },
    ],
    $liveWaitingLogs,
);
$liveWaitingRuntime->tick();
$liveWaitingLog = findRuntimeLog($liveWaitingLogs, 'node.result');
Assert::true($liveWaitingLog !== null, '直播 waiting 场景应记录 node.result 日志。');
Assert::true(str_contains((string)$liveWaitingLog['message'], '房间 2233'), '直播 waiting 日志应包含房间号。');
Assert::true(str_contains((string)$liveWaitingLog['message'], '120/240 秒'), '直播 waiting 日志应包含累计观看秒数。');
Assert::true(str_contains((string)$liveWaitingLog['message'], '30 秒后继续'), '直播 waiting 日志应包含等待秒数。');

/**
 * @param array<int, array{level: string, message: string, context: array<string, mixed>}> $logs
 * @return array{level: string, message: string, context: array<string, mixed>}|null
 */
function findRuntimeLog(array $logs, string $event): ?array
{
    foreach ($logs as $log) {
        if (($log['context']['event'] ?? '') === $event) {
            return $log;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $activity
 * @param array<int, ActivityNode> $nodes
 * @param array<int, array<string, mixed>> $tasks
 * @param array<int, NodeRunnerInterface> $runners
 * @param array<int, array{level: string, message: string, context: array<string, mixed>}> $logs
 */
function buildBusinessRuntime(
    string $scope,
    int $now,
    array $activity,
    array $nodes,
    array $tasks,
    array $runners,
    array &$logs,
): ActivityLotteryRuntime {
    $store = new ActivityFlowStore($scope);
    $flow = ActivityFlowFactory::create(
        ActivityCatalogItem::fromArray($activity),
        '2026-04-03',
        $nodes,
    );
    $row = $flow->toArray();
    $row['context'] = [
        'era_page_snapshot' => [
            'activity_id' => (string)($activity['activity_id'] ?? ''),
            'page_id' => (string)($activity['page_id'] ?? 'business-page'),
            'lottery_id' => (string)($activity['lottery_id'] ?? ''),
            'start_time' => 0,
            'end_time' => 0,
            'tasks' => $tasks,
        ],
    ];
    $store->save([ActivityFlow::fromArray($row)]);

    return new ActivityLotteryRuntime(
        new ActivityCatalogLoader([]),
        $store,
        $runners,
        new ActivityFlowPlanner(),
        new ActivityFlowPool(new ActivityFlowBudget(1, 1, 3000)),
        new ActivityLotteryClock(static fn (): int => $now),
        new ActivityLotteryWindow('06:00:00', '23:00:00'),
        '06:00:00',
        '23:00:00',
        static function (string $level, string $message, array $context = []) use (&$logs): void {
            $logs[] = [
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ];
        },
    );
}
