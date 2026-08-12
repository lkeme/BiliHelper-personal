<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Runtime;

use Bhp\Automation\Follow\BizDate;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Gateway\TaskProgressGateway;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Page\CarnivalPageResolver;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Page\CarnivalSnapshot;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\State\CarnivalStateStore;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\CarnivalContext;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\CarnivalStepResult;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\TaskRunnerInterface;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task\WatchLiveTaskRunner;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\RequestException;

/**
 * 编排层。
 *
 * 每日时间窗口不在此处判断 —— 框架 Plugin::canItRun() 已用 plugin.json 的 start/end
 * 做过 isWithinTimeRange 门禁，窗口外 runTask() 直接返回 keepSchedule()。
 *
 * 本类负责：快照缓存、接管冲突自守卫、单次 totalv2 批量查询、runner 分派与 tick 预算、
 * 以及把各 runner 的结果聚合成一个 TaskResult。
 */
final class CarnivalRuntime
{
    private const SNAPSHOT_TTL_SECONDS = 86400;
    private const MAX_STEPS_PER_TICK = 3;
    private const MAX_RUNTIME_MS_PER_TICK = 3000;
    private const IDLE_DELAY_SECONDS = 600.0;
    private const CONFLICT_DELAY_SECONDS = 21600.0;

    /** @var TaskRunnerInterface[] */
    private readonly array $runners;

    /** @var \Closure(string, string, array<string, mixed>): void */
    private readonly \Closure $logger;

    /** @var \Closure(): TaskResult */
    private readonly \Closure $nextDayTaskResultFactory;

    /**
     * @param TaskRunnerInterface[] $runners 按价值排序
     * @param callable(string, string, array<string, mixed>): void $logger
     * @param callable(): TaskResult $nextDayTaskResultFactory
     */
    public function __construct(
        private readonly CarnivalPageResolver $pageResolver,
        private readonly TaskProgressGateway $taskProgressGateway,
        private readonly CarnivalStateStore $stateStore,
        private readonly CatalogConflictGuard $conflictGuard,
        array $runners,
        callable $logger,
        callable $nextDayTaskResultFactory,
    ) {
        $this->runners = array_values($runners);
        $this->logger = \Closure::fromCallable($logger);
        $this->nextDayTaskResultFactory = \Closure::fromCallable($nextDayTaskResultFactory);
    }

    /**
     * 推进一个 tick
     */
    public function tick(): TaskResult
    {
        if ($this->conflictGuard->hasConflict()) {
            $this->log('warning', '次元奇旅: 检测到与 ActivityLottery 的接管冲突，本插件暂不执行', [
                'reason' => $this->conflictGuard->describe(),
            ]);

            return TaskResult::after(self::CONFLICT_DELAY_SECONDS, '与 ActivityLottery 接管冲突');
        }

        $now = time();
        $bizDate = BizDate::today($now);

        try {
            $snapshot = $this->resolveSnapshot($now);
        } catch (RequestException $exception) {
            $this->log('warning', '次元奇旅: 活动配置获取失败', ['error' => $exception->getMessage()]);

            return TaskResult::retryAfter(900.0, $exception->getMessage());
        }

        if (!$snapshot->isUsable()) {
            $this->log('warning', '次元奇旅: 活动配置关键字段缺失，跳过本轮', [
                'fallback' => implode(',', $snapshot->fallbackFields),
            ]);

            return TaskResult::after(3600.0, '活动配置不可用');
        }

        // 观看会话续跑快车道：只发一次心跳后立即返回。
        // 此路径刻意不查 totalv2 —— 心跳间隔约 60s，5 分钟要跑 ~10 轮，
        // 每轮都查一次任务进度纯属浪费，且会挤占其他插件的请求预算。
        // 当日进度由本地 watch_progress 精确累计（心跳是本插件自己发的），
        // 达标后会清空会话，下一个完整 tick 再走权威校验。
        if ($this->stateStore->watchSession() !== null) {
            $fastPath = $this->runWatchHeartbeat(new CarnivalContext($snapshot, [], $bizDate, $now));
            if ($fastPath instanceof TaskResult) {
                return $fastPath;
            }
        }

        try {
            $taskDetails = $this->taskProgressGateway->fetch($snapshot->trackedTaskIds());
        } catch (RequestException $exception) {
            $this->log('warning', '次元奇旅: 任务进度同步失败', ['error' => $exception->getMessage()]);

            return TaskResult::retryAfter(900.0, $exception->getMessage());
        }

        $context = new CarnivalContext($snapshot, $taskDetails, $bizDate, $now);

        return $this->dispatch($context);
    }

    /**
     * 观看心跳快车道。
     *
     * 返回 TaskResult 表示本 tick 就此结束；返回 null 表示会话已收尾，
     * 应继续走完整流程（查进度 + 其余 runner）。
     */
    private function runWatchHeartbeat(CarnivalContext $context): ?TaskResult
    {
        $watchRunner = $this->findRunner(WatchLiveTaskRunner::class);
        if ($watchRunner === null) {
            return null;
        }

        $result = $this->runOne($watchRunner, $context);
        if ($result->failed) {
            return TaskResult::retryAfter($result->delaySeconds ?? 900.0, (string)$result->message);
        }
        if ($result->delaySeconds !== null) {
            return TaskResult::after($result->delaySeconds, (string)$result->message);
        }

        return null;
    }

    /**
     * 分派 runner 并聚合结果
     */
    private function dispatch(CarnivalContext $context): TaskResult
    {
        $startedAt = microtime(true);
        $steps = 0;
        $executed = 0;
        $delays = [];
        $messages = [];

        foreach ($this->runners as $runner) {
            if ($steps >= self::MAX_STEPS_PER_TICK) {
                break;
            }
            if ((microtime(true) - $startedAt) * 1000 >= self::MAX_RUNTIME_MS_PER_TICK) {
                break;
            }

            $result = $this->runOne($runner, $context);
            if ($result->message !== null) {
                $messages[] = (string)$result->message;
            }

            if ($result->failed) {
                return TaskResult::retryAfter($result->delaySeconds ?? 900.0, (string)$result->message);
            }

            if ($result->delaySeconds !== null) {
                $delays[] = $result->delaySeconds;
            }

            if ($result->executed) {
                $executed++;
                $steps++;
            }
        }

        if ($messages !== []) {
            $this->log('debug', '次元奇旅: 本轮任务结果', ['steps' => implode(' / ', $messages)]);
        }

        if ($delays !== []) {
            return TaskResult::after(min($delays), '存在待续任务');
        }

        if ($executed > 0) {
            return TaskResult::after(self::IDLE_DELAY_SECONDS, '本轮已执行任务');
        }

        // 本轮无任何可执行任务 —— 今日事项已办完，交由下一个活动开始时间唤醒
        return ($this->nextDayTaskResultFactory)();
    }

    /**
     * 执行单个 runner，异常统一收敛
     */
    private function runOne(TaskRunnerInterface $runner, CarnivalContext $context): CarnivalStepResult
    {
        try {
            return $runner->run($context);
        } catch (RequestException $exception) {
            $this->log('warning', "次元奇旅: {$runner->key()} 请求异常", ['error' => $exception->getMessage()]);

            return CarnivalStepResult::failed(900.0, "{$runner->key()} 请求异常: {$exception->getMessage()}");
        }
    }

    /**
     * @param class-string $className
     */
    private function findRunner(string $className): ?TaskRunnerInterface
    {
        foreach ($this->runners as $runner) {
            if ($runner instanceof $className) {
                return $runner;
            }
        }

        return null;
    }

    /**
     * 读取快照，24h 内复用缓存
     */
    private function resolveSnapshot(int $now): CarnivalSnapshot
    {
        $cached = $this->stateStore->snapshot();
        if (is_array($cached)) {
            $snapshot = CarnivalSnapshot::fromArray($cached);
            $fresh = ($now - $snapshot->resolvedAt) < self::SNAPSHOT_TTL_SECONDS;
            if ($fresh && $snapshot->isUsable()) {
                return $snapshot;
            }
        }

        $snapshot = $this->pageResolver->resolve($now);
        $this->stateStore->putSnapshot($snapshot->toArray());

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        ($this->logger)($level, $message, $context);
    }
}
