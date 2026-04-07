<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery;

use Bhp\Api\Api\X\Activity\ApiActivity;
use Bhp\Api\Api\X\ActivityComponents\ApiMission;
use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Api\Api\X\Player\ApiPlayer;
use Bhp\Api\Api\X\Task\ApiTask as EraTaskApi;
use Bhp\Api\Dynamic\ApiTopic;
use Bhp\Api\Video\ApiWatch;
use Bhp\Api\XLive\DataInterface\V1\X25Kn\ApiTrace;
use Bhp\Api\XLive\WebInterface\V1\Second\ApiList;
use Bhp\Api\XLive\WebInterface\V1\WebMain\ApiRecommend;
use Bhp\Api\XLive\WebRoom\V1\Index\ApiIndex;
use Bhp\Automation\Watch\LiveWatchService;
use Bhp\Automation\Watch\VideoWatchService;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\ActivityCatalogLoader;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\ActivityCatalogValidator;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\LocalCatalogSource;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\RemoteCatalogSource;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStore;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowPlanner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\WatchVideoGateway;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\WatchLiveGateway;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraFollowNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraWatchLiveNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraWatchVideoNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraUnfollowNodeRunner;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Pool\ActivityFlowBudget;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Pool\ActivityFlowPool;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Runtime\ActivityLotteryClock;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Runtime\ActivityLotteryRuntime;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Runtime\ActivityLotteryWindow;
use Bhp\Remote\RemoteResourceResolver;
use Bhp\Scheduler\TaskResult;

final class ActivityLotteryPlugin extends BasePlugin implements PluginTaskInterface
{
    private const WINDOW_START = '06:00:00';
    private const WINDOW_END = '23:00:00';
    private const MAX_FLOWS_PER_TICK = 4;
    private const MAX_STEPS_PER_TICK = 6;
    private const MAX_RUNTIME_MS_PER_TICK = 3000;

    private ?ActivityLotteryRuntime $runtimeInstance = null;

    public function __construct(Plugin &$plugin)
    {
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

        $remoteResourceResolver = new RemoteResourceResolver($this->appContext());
        $remoteCatalogUrls = $remoteResourceResolver->resourceRawUrls('activity_infos.json');
        $logger = function (string $level, string $message, array $context = []): void {
            $context = array_replace(['caller' => 'ActivityLottery'], $context);
            switch (strtolower(trim($level))) {
                case 'warning':
                    $this->appContext()->log()->recordWarning($message, $context);
                    return;
                case 'notice':
                    $this->appContext()->log()->recordNotice($message, $context);
                    return;
                case 'error':
                    $this->appContext()->log()->recordError($message, $context);
                    return;
                case 'debug':
                    $this->appContext()->log()->recordDebug($message, $context);
                    return;
                default:
                    $this->appContext()->log()->recordInfo($message, $context);
                    return;
            }
        };
        $remoteCatalogFetcher = fn (string $url): string => $this->appContext()->request()->getText('other', $url);
        $sources = [
            new LocalCatalogSource($this->activityInfosLocalPath()),
            new RemoteCatalogSource(
                $remoteCatalogUrls,
                true,
                fetcher: $remoteCatalogFetcher,
            ),
        ];
        $noticePusher = function (string $channel, string $message): void {
            $this->notify($channel, $message);
        };
        $activityGateway = new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\ActivityLotteryGateway(
            pageHtmlFetcher: $remoteCatalogFetcher,
            noticePusher: $noticePusher,
        );
        $userAgentResolver = fn (): string => (string)$this->appContext()->device('platform.headers.pc_ua');
        $request = $this->appContext()->request();
        $activityApi = new ApiActivity($request);
        $missionApi = new ApiMission($request);
        $taskApi = new EraTaskApi($request);
        $playerApi = new ApiPlayer($request);
        $topicApi = new ApiTopic($request);
        $liveIndexApi = new ApiIndex($request);
        $liveTraceApi = new ApiTrace($request);
        $liveListApi = new ApiList($request);
        $liveRecommendApi = new ApiRecommend($request);
        $relationApi = new ApiRelation($this->appContext()->request());
        $drawGateway = new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\DrawGateway($activityApi);
        $eraTaskGateway = new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\EraTaskGateway($missionApi);
        $taskProgressGateway = new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\EraTaskProgressGateway($taskApi);
        $watchLiveGateway = new WatchLiveGateway(
            apiList: $liveListApi,
            apiRecommend: $liveRecommendApi,
            apiIndex: $liveIndexApi,
            watchService: new LiveWatchService($liveIndexApi, $liveTraceApi, $userAgentResolver),
            areaTagPageFetcher: $remoteCatalogFetcher,
            logger: $logger,
        );
        $watchVideoGateway = new WatchVideoGateway(
            apiPlayer: $playerApi,
            apiTopic: $topicApi,
            watchService: new VideoWatchService(
                apiWatch: new ApiWatch($this->appContext()->request()),
            ),
        );

        $this->runtimeInstance = new ActivityLotteryRuntime(
            new ActivityCatalogLoader($sources, new ActivityCatalogValidator($logger)),
            new ActivityFlowStore(
                rtrim(str_replace('\\', '/', $this->appContext()->cachePath()), '/') . '/cache.sqlite3',
                'ActivityLottery',
            ),
            [
                new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\LoadActivitySnapshotNodeRunner($activityGateway),
                new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ParseEraPageNodeRunner(taskProgressGateway: $taskProgressGateway),
                new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraShareNodeRunner($activityApi),
                new EraFollowNodeRunner(apiRelation: $relationApi),
                new EraUnfollowNodeRunner(apiRelation: $relationApi),
                new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\EraClaimRewardNodeRunner($eraTaskGateway),
                new EraWatchVideoNodeRunner('era_task_watch_video_fixed', $watchVideoGateway),
                new EraWatchVideoNodeRunner('era_task_watch_video_topic', $watchVideoGateway),
                new EraWatchLiveNodeRunner($watchLiveGateway),
                new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\RefreshDrawTimesNodeRunner($drawGateway),
                new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\ExecuteDrawNodeRunner($drawGateway),
                new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\NotifyDrawResultNodeRunner($activityGateway),
                new \Bhp\Plugin\Builtin\ActivityLottery\Internal\Node\FinalClaimRewardNodeRunner($eraTaskGateway),
            ],
            new ActivityFlowPlanner(),
            new ActivityFlowPool(new ActivityFlowBudget(
                self::MAX_FLOWS_PER_TICK,
                self::MAX_STEPS_PER_TICK,
                self::MAX_RUNTIME_MS_PER_TICK,
            )),
            new ActivityLotteryClock(),
            new ActivityLotteryWindow(self::WINDOW_START, self::WINDOW_END),
            self::WINDOW_START,
            self::WINDOW_END,
            $logger,
        );

        return $this->runtimeInstance;
    }

    private function activityInfosLocalPath(): string
    {
        return rtrim(str_replace('\\', '/', $this->appContext()->appRoot()), '/') . '/resources/activity_infos.json';
    }
}

