<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival;

use Bhp\Api\Api\X\Activity\ApiActivity;
use Bhp\Api\Api\X\ActivityComponents\ApiEvaOperation;
use Bhp\Api\Api\X\ActivityComponents\ApiMission;
use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Api\Api\X\Task\ApiTask;
use Bhp\Api\XLive\DataInterface\V1\X25Kn\ApiTrace;
use Bhp\Api\XLive\WebRoom\V1\Index\ApiIndex;
use Bhp\Automation\Follow\TemporaryFollowStore;
use Bhp\Automation\Follow\UnfollowQueueStore;
use Bhp\Automation\Watch\LiveWatchService;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Follow\FollowStateResolver;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Gateway\TaskProgressGateway;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Page\CarnivalPageResolver;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Runtime\CarnivalRuntime;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Runtime\CatalogConflictGuard;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\State\CarnivalStateStore;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\ClaimRewardTaskRunner;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\CleanupFollowTaskRunner;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\DrawTaskRunner;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\FollowTaskRunner;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\SignInTaskRunner;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\WatchLiveTaskRunner;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

/**
 * 2026「次元奇旅」暑期狂欢节。
 *
 * 入口只做开关判断与 runtime 装配；每日时间窗口由框架依据 plugin.json 的 start/end 门禁。
 * 活动 2026-09-30 结束后由 manifest 的 valid_until 自动跳过装配，无需手工下线。
 */
final class EraSummerCarnivalPlugin extends BasePlugin implements PluginTaskInterface
{
    private const CONFIG_KEY = 'era_summer_carnival';
    private const FOLLOW_SCOPE = 'EraSummerCarnival';

    private ?CarnivalRuntime $runtimeInstance = null;

    /**
     * 初始化 EraSummerCarnivalPlugin
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    /**
     * 执行一次任务
     * @return TaskResult
     */
    public function runOnce(): TaskResult
    {
        $this->resetTaskResult();

        if (!$this->enabled(self::CONFIG_KEY)) {
            return TaskResult::keepSchedule();
        }

        return $this->resolveTaskResult($this->runtime()->tick());
    }

    /**
     * 装配 runtime
     * @return CarnivalRuntime
     */
    private function runtime(): CarnivalRuntime
    {
        if ($this->runtimeInstance instanceof CarnivalRuntime) {
            return $this->runtimeInstance;
        }

        $context = $this->appContext();
        $request = $context->request();
        $logger = $this->makeLogger();
        $notifier = function (string $channel, string $message): void {
            $this->notify($channel, $message);
        };

        $accountKey = $this->uid();
        $cacheDatabasePath = rtrim(str_replace('\\', '/', $context->cachePath()), '/') . '/cache.sqlite3';
        $stateStore = new CarnivalStateStore($this->cache(), $accountKey);
        $temporaryFollowStore = new TemporaryFollowStore($cacheDatabasePath, self::FOLLOW_SCOPE);
        $unfollowQueueStore = new UnfollowQueueStore($cacheDatabasePath, self::FOLLOW_SCOPE);

        $apiActivity = new ApiActivity($request);
        $apiMission = new ApiMission($request);
        $apiRelation = new ApiRelation($request);
        $apiEvaOperation = new ApiEvaOperation($request);
        $apiIndex = new ApiIndex($request);
        $apiTrace = new ApiTrace($request);

        $taskProgressGateway = new TaskProgressGateway(new ApiTask($request));
        $followStateResolver = new FollowStateResolver($apiRelation);

        $liveWatchService = new LiveWatchService(
            $apiIndex,
            $apiTrace,
            fn (): string => (string)$context->device('platform.headers.pc_ua'),
        );

        $pageResolver = new CarnivalPageResolver(
            fn (string $url): string => $request->getText('pc', $url, [], ['Referer' => $url]),
            $logger,
        );

        $runners = [
            new SignInTaskRunner($apiActivity, $taskProgressGateway, $stateStore, $logger),
            new ClaimRewardTaskRunner($apiMission, $stateStore, $logger, $notifier),
            new DrawTaskRunner($apiActivity, $stateStore, $logger, $notifier),
            new WatchLiveTaskRunner(
                $apiEvaOperation,
                $apiIndex,
                $liveWatchService,
                $stateStore,
                $followStateResolver,
                $logger,
            ),
            new FollowTaskRunner(
                $apiRelation,
                $followStateResolver,
                $temporaryFollowStore,
                $unfollowQueueStore,
                $accountKey,
                $logger,
            ),
            new CleanupFollowTaskRunner(
                $apiRelation,
                $followStateResolver,
                $temporaryFollowStore,
                $unfollowQueueStore,
                $accountKey,
                $logger,
            ),
        ];

        $this->runtimeInstance = new CarnivalRuntime(
            $pageResolver,
            $taskProgressGateway,
            $stateStore,
            new CatalogConflictGuard(
                $context->appRoot(),
                $context->enabled('activity_lottery'),
            ),
            $runners,
            $logger,
            fn (): TaskResult => $this->nextPluginStartTaskResult(1, 60, true, '今日任务已办完'),
        );

        return $this->runtimeInstance;
    }

    /**
     * 统一日志出口
     *
     * @return \Closure(string, string, array<string, mixed>): void
     */
    private function makeLogger(): \Closure
    {
        return function (string $level, string $message, array $logContext = []): void {
            $logContext = array_replace(['caller' => 'EraSummerCarnival'], $logContext);
            match (strtolower(trim($level))) {
                'error' => $this->error($message, $logContext),
                'warning' => $this->warning($message, $logContext),
                'notice' => $this->notice($message, $logContext),
                'debug' => $this->debug($message, $logContext),
                default => $this->info($message, $logContext),
            };
        };
    }
}
