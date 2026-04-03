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
