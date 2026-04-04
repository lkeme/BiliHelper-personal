<?php declare(strict_types=1);

require_once __DIR__ . '/Internal/bootstrap.php';

use Bhp\Cache\Cache;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\LocalCatalogSource;
use Bhp\Plugin\ActivityLottery\Internal\Catalog\RemoteCatalogSource;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowStore;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowBudget;
use Bhp\Plugin\ActivityLottery\Internal\Pool\ActivityFlowPool;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryClock;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryRuntime;
use Bhp\Plugin\ActivityLottery\Internal\Runtime\ActivityLotteryWindow;
use Bhp\Remote\RemoteResourceResolver;
use Bhp\Scheduler\TaskResult;

class ActivityLottery extends BasePlugin implements PluginTaskInterface
{
    public ?array $info = [
        'hook' => __CLASS__,
        'name' => 'ActivityLottery',
        'version' => '0.0.2',
        'desc' => '转盘活动',
        'author' => 'Lkeme',
        'priority' => 1117,
        'cycle' => '1-5(分钟)',
        'start' => '06:00:00',
        'end' => '23:00:00',
    ];

    private ?ActivityLotteryRuntime $runtimeInstance = null;

    public function __construct(Plugin &$plugin)
    {
        Cache::initCache();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('activity_lottery')) {
            return TaskResult::keepSchedule();
        }

        return $this->runtime()->tick();
    }

    protected function runtime(): ActivityLotteryRuntime
    {
        if ($this->runtimeInstance instanceof ActivityLotteryRuntime) {
            return $this->runtimeInstance;
        }

        $windowStart = trim((string)$this->config('activity_lottery.window_start', '06:00:00', 'string'));
        $windowEnd = trim((string)$this->config('activity_lottery.window_end', '23:00:00', 'string'));
        $remoteCatalogUrl = trim((string)$this->config('activity_lottery.remote_catalog_url', '', 'string'));
        if ($remoteCatalogUrl === '') {
            $remoteCatalogUrl = (new RemoteResourceResolver())->resourceRawUrl('activity_infos.json');
        }
        $sources = [
            new LocalCatalogSource($this->activityInfosLocalPath()),
            new RemoteCatalogSource(
                $remoteCatalogUrl,
                true,
            ),
        ];

        $this->runtimeInstance = new ActivityLotteryRuntime(
            new ActivityCatalogLoader($sources),
            new ActivityFlowStore('ActivityLottery'),
            [],
            new ActivityFlowPlanner(),
            new ActivityFlowPool(new ActivityFlowBudget(
                max(1, (int)$this->config('activity_lottery.max_flows_per_tick', 4, 'int')),
                max(1, (int)$this->config('activity_lottery.max_steps_per_tick', 6, 'int')),
                max(1, (int)$this->config('activity_lottery.max_runtime_ms_per_tick', 3000, 'int')),
            )),
            new ActivityLotteryClock(),
            new ActivityLotteryWindow($windowStart, $windowEnd),
            $windowStart,
            $windowEnd,
        );

        return $this->runtimeInstance;
    }

    private function activityInfosLocalPath(): string
    {
        return rtrim(str_replace('\\', '/', $this->appContext()->appRoot()), '/') . '/resources/activity_infos.json';
    }
}
