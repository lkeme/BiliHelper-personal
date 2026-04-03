<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__, 2) . '/plugin/ActivityLottery/ActivityLottery.php';

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\CatalogSourceInterface;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
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
$scope = 'ActivityLotteryRuntimeTest_' . substr(md5((string)$now), 0, 8);
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
);

Assert::same('2026-04-03', $runtime->bizDate(), 'runtime 应暴露当前 bizDate。');
$tickResult = $runtime->tick();
Assert::true($tickResult instanceof TaskResult, 'runtime->tick() 应返回 TaskResult。');
Assert::true($tickResult->nextRunAfterSeconds !== null, 'runtime->tick() 应给出下一次调度间隔。');
$storedFlows = $store->load('2026-04-03');
Assert::same(1, count($storedFlows), 'runtime->tick() 应创建并保存当天 flow。');
Assert::same(1, $storedFlows[0]->currentNodeIndex(), 'runtime->tick() 推进一步后应推进 current_node_index。');
Assert::same(ActivityNodeStatus::SUCCEEDED, $storedFlows[0]->nodes()[0]->status(), '已执行节点应写回 succeeded。');

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
);
$outsideResult = $outsideRuntime->tick();
Assert::true($outsideResult->nextRunAfterSeconds !== null, '窗口外 tick 应直接返回下次窗口调度时间。');

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
