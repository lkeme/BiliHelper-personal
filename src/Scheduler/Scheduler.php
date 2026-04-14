<?php declare(strict_types=1);

namespace Bhp\Scheduler;

use Bhp\Http\HttpRequestTrafficMonitor;
use Bhp\Login\LoginGateStateService;
use Bhp\Login\LoginManualInterventionPolicy;
use Bhp\Log\Log;
use Bhp\Plugin\Plugin;
use Bhp\Util\AppTerminator;
use Bhp\Util\Exceptions\NoLoginException;
use InvalidArgumentException;
use Revolt\EventLoop;
use Throwable;
use function Amp\async;

class Scheduler
{
    private const CIRCUIT_FAILURE_THRESHOLD = 3;
    private const CIRCUIT_OPEN_SECONDS = 300.0;
    private const LOGIN_REQUIRED_RETRY_SECONDS = 30.0;
    private const UNEXPECTED_FAILURE_RETRY_SECONDS = 60.0;

    /** @var array<string, ScheduledTask> */
    private array $tasks = [];

    /** @var array<string, int> */
    private array $running = [];

    private bool $started = false;
    private bool $loginRecoveryRequested = false;
    private ?SchedulerStateStore $stateStore = null;
    private ?LoginGateStateService $loginGateStateService = null;
    private ?LoginManualInterventionPolicy $loginManualInterventionPolicy = null;

    /**
     * 初始化 Scheduler
     * @param Plugin $plugin
     * @param Log $log
     * @param LoginGateStateService $loginGateStateService
     * @param LoginManualInterventionPolicy $loginManualInterventionPolicy
     * @param HttpRequestTrafficMonitor $httpRequestTrafficMonitor
     * @param SchedulerStateStore $schedulerStateStore
     */
    public function __construct(
        private readonly Plugin $plugin,
        private readonly Log $log,
        LoginGateStateService $loginGateStateService,
        LoginManualInterventionPolicy $loginManualInterventionPolicy,
        private readonly HttpRequestTrafficMonitor $httpRequestTrafficMonitor,
        private readonly SchedulerStateStore $schedulerStateStore,
    ) {
        $this->loginGateStateService = $loginGateStateService;
        $this->loginManualInterventionPolicy = $loginManualInterventionPolicy;
    }

    /**
     * 注册Plugins
     * @param array $plugins
     * @return void
     */
    public function registerPlugins(array $plugins): void
    {
        $now = $this->monotonicNowNs();
        $nowEpoch = $this->wallTimeNowSeconds();
        $validatedPlugins = array_map(
            fn (mixed $plugin): array => $this->validatePluginDefinition($plugin),
            $plugins
        );
        $states = $this->stateStore()->load();
        foreach ($validatedPlugins as $plugin) {
            $hook = (string)$plugin['hook'];
            $intervalSeconds = isset($plugin['interval_seconds']) && is_numeric($plugin['interval_seconds'])
                ? max(0.05, (float)$plugin['interval_seconds'])
                : $this->parseCycleToSeconds((string)$plugin['cycle']);
            $taskState = $states[$hook] ?? null;
            $bootstrapFirst = (bool)($plugin['bootstrap_first'] ?? false);

            $nextRunAtNs = $this->restoreDeadlineFromState($taskState['next_run_at'] ?? null, $now, $nowEpoch);
            $failureCount = isset($taskState['failure_count']) ? max(0, (int)$taskState['failure_count']) : 0;
            $circuitOpenUntilNs = $this->restoreDeadlineFromState($taskState['circuit_open_until'] ?? null, $now, $nowEpoch, 0.0);
            if ($bootstrapFirst) {
                $nextRunAtNs = $now;
                $failureCount = 0;
                $circuitOpenUntilNs = 0.0;
            }

            $this->tasks[$hook] = new ScheduledTask(
                $hook,
                (string)$plugin['name'],
                (int)($plugin['priority'] ?? 9999),
                $intervalSeconds,
                (string)($plugin['overrun_policy'] ?? TaskPolicy::SKIP),
                max(1, (int)($plugin['max_concurrency'] ?? 1)),
                max(0.1, (float)($plugin['timeout_seconds'] ?? 30.0)),
                $nextRunAtNs,
                $intervalSeconds < 1.0,
                $bootstrapFirst,
                array_values(array_filter($plugin['governance_hosts'] ?? [], 'is_string')),
                max(0, (int)($plugin['governance_window_seconds'] ?? 0)),
                max(0, (int)($plugin['governance_max_requests_per_host'] ?? 0)),
                max(0, (int)($plugin['governance_cooldown_seconds'] ?? 0)),
                (string)($plugin['governance_group'] ?? ''),
                max(0, (int)($plugin['governance_group_max_concurrency'] ?? 0)),
                (string)($plugin['governance_profile'] ?? ''),
                max(0.0, (float)($plugin['governance_group_backoff_seconds'] ?? 0.0)),
                max(0.0, (float)($plugin['governance_cooldown_multiplier'] ?? 0.0)),
                $failureCount,
                $circuitOpenUntilNs,
            );
        }
    }

    /**
     * 启动执行流程
     * @return void
     */
    public function run(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->runInitialRound();
        if ($this->hasHighFrequencyTasks()) {
            EventLoop::repeat(0.05, fn () => $this->tick(true));
        }
        EventLoop::repeat(1.0, fn () => $this->tick(false));

        EventLoop::run();
    }

    /**
     * @return array<string, int>
     */
    public function diagnosticsSummary(): array
    {
        $now = $this->monotonicNowNs();

        return [
            'scheduler_tasks' => count($this->tasks),
            'high_frequency_tasks' => count(array_filter($this->tasks, static fn(ScheduledTask $task): bool => $task->highFrequency)),
            'bootstrap_first_tasks' => count(array_filter($this->tasks, static fn(ScheduledTask $task): bool => $task->bootstrapFirst)),
            'concurrent_tasks' => count(array_filter($this->tasks, static fn(ScheduledTask $task): bool => $task->maxConcurrency > 1)),
            'open_circuits' => count(array_filter($this->tasks, static fn(ScheduledTask $task): bool => $task->circuitOpenUntilNs > $now)),
            'failing_tasks' => count(array_filter($this->tasks, static fn(ScheduledTask $task): bool => $task->failureCount > 0)),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function diagnosticsIssueRows(): array
    {
        $now = $this->monotonicNowNs();
        $rows = [];

        foreach ($this->tasks as $task) {
            $circuitOpen = $task->circuitOpenUntilNs > $now;
            if (!$circuitOpen && $task->failureCount === 0) {
                continue;
            }

            $rows[] = [
                'hook' => $task->hook,
                'name' => $task->name,
                'failure_count' => (string)$task->failureCount,
                'circuit_state' => $circuitOpen ? 'open' : 'closed',
                'circuit_remaining_seconds' => $circuitOpen ? (string)round(($task->circuitOpenUntilNs - $now) / 1_000_000_000, 2) : '0',
                'next_run_in_seconds' => $this->formatNextRunDelay($task, $now),
            ];
        }

        return $rows;
    }

    /**
     * 处理运行InitialRound
     * @return void
     */
    private function runInitialRound(): void
    {
        $this->loginManualInterventionPolicy()->enforce();
        $tasks = array_values($this->tasks);
        usort($tasks, function (ScheduledTask $left, ScheduledTask $right): int {
            if ($left->bootstrapFirst !== $right->bootstrapFirst) {
                return $left->bootstrapFirst ? -1 : 1;
            }

            return $left->priority <=> $right->priority;
        });

        $now = $this->monotonicNowNs();
        foreach ($tasks as $task) {
            if ($this->shouldHoldTaskForLoginPendingFlow($task)) {
                continue;
            }

            if ($task->nextRunAtNs > $now) {
                continue;
            }

            if ($this->isCircuitOpen($task, $now)) {
                $this->persistTaskState($task);
                continue;
            }

            if ($this->applyGovernanceBackoff($task, $now)) {
                $this->persistTaskState($task);
                continue;
            }

            if ($this->applyGroupConcurrencyBackoff($task, $now)) {
                $this->persistTaskState($task);
                continue;
            }

            $result = $this->dispatchSync($task, $now);
            $this->applyTaskResult($task, $result, $this->monotonicNowNs());
        }
    }

    /**
     * 处理tick
     * @param bool $highFrequency
     * @return void
     */
    private function tick(bool $highFrequency): void
    {
        $this->loginManualInterventionPolicy()->enforce();
        $now = $this->monotonicNowNs();
        foreach ($this->tasks as $task) {
            if ($this->shouldHoldTaskForLoginPendingFlow($task)) {
                continue;
            }

            if ($task->highFrequency !== $highFrequency) {
                continue;
            }

            if ($task->nextRunAtNs > $now) {
                continue;
            }

            if ($this->isCircuitOpen($task, $now)) {
                $this->persistTaskState($task);
                continue;
            }

            if ($this->applyGovernanceBackoff($task, $now)) {
                $this->persistTaskState($task);
                continue;
            }

            if ($this->applyGroupConcurrencyBackoff($task, $now)) {
                $this->persistTaskState($task);
                continue;
            }

            $this->dispatch($task, $now);
        }
    }

    /**
     * 判断HighFrequencyTasks是否满足条件
     * @return bool
     */
    private function hasHighFrequencyTasks(): bool
    {
        foreach ($this->tasks as $task) {
            if ($task->highFrequency) {
                return true;
            }
        }

        return false;
    }

    /**
     * 处理分发
     * @param ScheduledTask $task
     * @param float $nowNs
     * @return void
     */
    private function dispatch(ScheduledTask $task, float $nowNs): void
    {
        $running = $this->running[$task->hook] ?? 0;
        if ($running >= $task->maxConcurrency) {
            if ($task->policy === TaskPolicy::SKIP) {
                $this->log->recordDebug("[SCHEDULER] skip {$task->name} because previous execution is still running", [
                    'plugin' => $task->hook,
                    'task' => 'scheduler.dispatch',
                ]);
                $this->advanceNextRunAt($task, $nowNs);
            }
            return;
        }

        $this->reserveNextRunSlot($task, $nowNs);
        $this->running[$task->hook] = $running + 1;

        async(function () use ($task) {
            $startedAt = $this->monotonicNowNs();
            $result = TaskResult::keepSchedule();
            try {
                $this->log->recordDebug("[SCHEDULER] dispatch {$task->name} interval={$task->intervalSeconds}s", [
                    'plugin' => $task->hook,
                    'task' => 'scheduler.dispatch',
                ]);
                $result = $this->log->withScopedContext([
                    'plugin' => $task->hook,
                    'task' => 'plugin.run',
                ], fn () => $this->plugin->runTask($task->hook));
            } catch (NoLoginException $e) {
                if ($task->hook === 'Login') {
                    AppTerminator::fail("登录失败，终止运行: {$e->getMessage()}", [], 0);
                }

                $this->requestLoginRecovery($task, $this->monotonicNowNs(), $e->getMessage());
                $this->log->recordWarning("[SCHEDULER] {$task->name} login required: {$e->getMessage()}", [
                    'plugin' => $task->hook,
                    'task' => 'plugin.run',
                ]);
                $result = TaskResult::after(self::LOGIN_REQUIRED_RETRY_SECONDS);
            } catch (Throwable $e) {
                if ($task->hook === 'Login') {
                    AppTerminator::fail("登录异常，终止运行: {$e->getMessage()}", [], 0);
                }

                $this->log->recordError("[SCHEDULER] {$task->name} failed: {$e->getMessage()}", [
                    'plugin' => $task->hook,
                    'task' => 'plugin.run',
                ]);
                $result = TaskResult::retryAfter(self::UNEXPECTED_FAILURE_RETRY_SECONDS, $e->getMessage());
            } finally {
                $finishedAt = $this->monotonicNowNs();
                $durationSeconds = ($finishedAt - $startedAt) / 1_000_000_000;
                if ($durationSeconds > $task->timeoutSeconds) {
                    $this->log->recordWarning("[SCHEDULER] {$task->name} timeout exceeded {$durationSeconds}s > {$task->timeoutSeconds}s", [
                        'plugin' => $task->hook,
                        'task' => 'scheduler.dispatch',
                    ]);
                }
                $this->recordTaskOutcome($task, $result, $finishedAt);
                $this->applyTaskResult($task, $result, $finishedAt);
                $this->running[$task->hook] = max(0, ($this->running[$task->hook] ?? 1) - 1);
            }
        });
    }

    /**
     * 分发同步
     * @param ScheduledTask $task
     * @param float $nowNs
     * @return TaskResult
     */
    private function dispatchSync(ScheduledTask $task, float $nowNs): TaskResult
    {
        $this->log->recordDebug("[SCHEDULER.INIT] dispatch {$task->name} priority={$task->priority}", [
            'plugin' => $task->hook,
            'task' => 'scheduler.init',
        ]);

        $this->reserveNextRunSlot($task, $nowNs);
        $startedAt = $this->monotonicNowNs();
        $result = TaskResult::keepSchedule();
        try {
            $result = $this->log->withScopedContext([
                'plugin' => $task->hook,
                'task' => 'plugin.run',
            ], fn () => $this->plugin->runTask($task->hook));
        } catch (NoLoginException $e) {
            if ($task->hook === 'Login') {
                AppTerminator::fail("登录失败，终止启动: {$e->getMessage()}", [], 0);
            }

            $this->requestLoginRecovery($task, $this->monotonicNowNs(), $e->getMessage());
            $this->log->recordWarning("[SCHEDULER.INIT] {$task->name} login required: {$e->getMessage()}", [
                'plugin' => $task->hook,
                'task' => 'plugin.run',
            ]);
            $result = TaskResult::after(self::LOGIN_REQUIRED_RETRY_SECONDS);
        } catch (Throwable $e) {
            if ($task->hook === 'Login') {
                AppTerminator::fail("登录异常，终止启动: {$e->getMessage()}", [], 0);
            }

            $this->log->recordError("[SCHEDULER.INIT] {$task->name} failed: {$e->getMessage()}", [
                'plugin' => $task->hook,
                'task' => 'plugin.run',
            ]);
            $result = TaskResult::retryAfter(self::UNEXPECTED_FAILURE_RETRY_SECONDS, $e->getMessage());
        } finally {
            $durationSeconds = ($this->monotonicNowNs() - $startedAt) / 1_000_000_000;
            if ($durationSeconds > $task->timeoutSeconds) {
                $this->log->recordWarning("[SCHEDULER.INIT] {$task->name} timeout exceeded {$durationSeconds}s > {$task->timeoutSeconds}s", [
                    'plugin' => $task->hook,
                    'task' => 'scheduler.init',
                ]);
            }
        }

        $this->recordTaskOutcome($task, $result, $this->monotonicNowNs());

        return $result;
    }

    /**
     * 应用任务结果
     * @param ScheduledTask $task
     * @param TaskResult $result
     * @param float $referenceNs
     * @return void
     */
    private function applyTaskResult(ScheduledTask $task, TaskResult $result, float $referenceNs): void
    {
        if ($result->nextRunAfterSeconds !== null) {
            $delayNs = max(1.0, round(max(0.0, $result->nextRunAfterSeconds) * 1_000_000_000));
            $task->nextRunAtNs = $referenceNs + $delayNs;
            $this->persistTaskState($task);
            $this->refreshLoginRecoveryState($task);
            return;
        }

        $this->finalizeScheduledNextRun($task, $referenceNs);
        $this->persistTaskState($task);
        $this->refreshLoginRecoveryState($task);
    }

    /**
     * 预留下次运行Slot
     * @param ScheduledTask $task
     * @param float $referenceNs
     * @return void
     */
    private function reserveNextRunSlot(ScheduledTask $task, float $referenceNs): void
    {
        $this->advanceNextRunAt($task, $referenceNs);
    }

    /**
     * 完成Scheduled下次运行
     * @param ScheduledTask $task
     * @param float $referenceNs
     * @return void
     */
    private function finalizeScheduledNextRun(ScheduledTask $task, float $referenceNs): void
    {
        if ($task->nextRunAtNs === 0.0) {
            $this->advanceNextRunAt($task, $referenceNs);
            return;
        }

        if ($task->nextRunAtNs > $referenceNs) {
            return;
        }

        if ($task->policy === TaskPolicy::SERIALIZE) {
            $task->nextRunAtNs = $referenceNs;
            return;
        }

        $this->advanceNextRunAt($task, $referenceNs);
    }

    /**
     * 推进下次运行At
     * @param ScheduledTask $task
     * @param float $nowNs
     * @return void
     */
    private function advanceNextRunAt(ScheduledTask $task, float $nowNs): void
    {
        $intervalNs = max(1.0, round($task->intervalSeconds * 1_000_000_000));

        if ($task->nextRunAtNs === 0.0) {
            $task->nextRunAtNs = $nowNs + $intervalNs;
            return;
        }

        do {
            $task->nextRunAtNs += $intervalNs;
        } while ($task->nextRunAtNs <= $nowNs);
    }

    /**
     * 处理monotonic当前时间Ns
     * @return float
     */
    private function monotonicNowNs(): float
    {
        return (float) hrtime(true);
    }

    /**
     * 处理wall时间当前时间Seconds
     * @return float
     */
    private function wallTimeNowSeconds(): float
    {
        return microtime(true);
    }

    /**
     * 格式化下次运行延迟
     * @param ScheduledTask $task
     * @param float $nowNs
     * @return string
     */
    private function formatNextRunDelay(ScheduledTask $task, float $nowNs): string
    {
        if ($task->nextRunAtNs >= (float)PHP_INT_MAX) {
            return 'running';
        }

        return (string)round(max(0.0, ($task->nextRunAtNs - $nowNs) / 1_000_000_000), 2);
    }

    /**
     * 解析CycleToSeconds
     * @param string $cycle
     * @return float
     */
    private function parseCycleToSeconds(string $cycle): float
    {
        if ($cycle === '') {
            return 60.0;
        }

        preg_match('/(\d+)(?:-(\d+))?/', $cycle, $matches);
        $min = isset($matches[1]) ? (int)$matches[1] : 1;
        $max = isset($matches[2]) ? (int)$matches[2] : $min;
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        $value = $min === $max ? $min : mt_rand($min, $max);

        return match (true) {
            str_contains($cycle, '秒') => max(0.05, (float)$value),
            str_contains($cycle, '分钟') => max(1.0, (float)$value * 60),
            str_contains($cycle, '小时') => max(1.0, (float)$value * 3600),
            default => max(1.0, (float)$value * 60),
        };
    }

    /**
     * 判断CircuitOpen是否满足条件
     * @param ScheduledTask $task
     * @param float $nowNs
     * @return bool
     */
    private function isCircuitOpen(ScheduledTask $task, float $nowNs): bool
    {
        if ($task->circuitOpenUntilNs <= $nowNs) {
            if ($task->circuitOpenUntilNs > 0.0) {
                $this->log->recordNotice("[SCHEDULER] {$task->name} circuit closed, resume scheduling", [
                    'plugin' => $task->hook,
                    'task' => 'scheduler.circuit',
                ]);
                $task->circuitOpenUntilNs = 0.0;
                $task->failureCount = 0;
            }

            return false;
        }

        if ($task->nextRunAtNs <= $nowNs) {
            $task->nextRunAtNs = $task->circuitOpenUntilNs;
        }

        return true;
    }

    /**
     * 记录任务Outcome
     * @param ScheduledTask $task
     * @param TaskResult $result
     * @param float $referenceNs
     * @return void
     */
    private function recordTaskOutcome(ScheduledTask $task, TaskResult $result, float $referenceNs): void
    {
        if ($result->success) {
            $task->failureCount = 0;
            return;
        }

        $task->failureCount++;
        if ($task->failureCount < self::CIRCUIT_FAILURE_THRESHOLD) {
            return;
        }

        $task->failureCount = 0;
        $task->circuitOpenUntilNs = $referenceNs + (self::CIRCUIT_OPEN_SECONDS * 1_000_000_000);
        if ($task->nextRunAtNs < $task->circuitOpenUntilNs) {
            $task->nextRunAtNs = $task->circuitOpenUntilNs;
        }

        $this->log->recordWarning("[SCHEDULER] {$task->name} circuit opened for " . self::CIRCUIT_OPEN_SECONDS . "s", [
            'plugin' => $task->hook,
            'task' => 'scheduler.circuit',
        ]);
    }

    /**
     * 应用治理Backoff
     * @param ScheduledTask $task
     * @param float $nowNs
     * @return bool
     */
    private function applyGovernanceBackoff(ScheduledTask $task, float $nowNs): bool
    {
        if ($task->governanceHosts === []
            || $task->governanceWindowSeconds < 1
            || $task->governanceMaxRequestsPerHost < 1
            || $task->governanceCooldownSeconds < 1) {
            return false;
        }

        $remaining = 0.0;
        foreach ($task->governanceHosts as $host) {
            $hostRemaining = $this->httpRequestTrafficMonitor->cooldownRemaining(
                $host,
                $task->governanceWindowSeconds,
                $task->governanceMaxRequestsPerHost,
                $task->governanceCooldownSeconds,
            );
            $remaining = max($remaining, $hostRemaining);
        }

        if ($remaining <= 0.0) {
            return false;
        }

        $delaySeconds = $remaining * $this->resolveGovernanceCooldownMultiplier($task);
        $task->nextRunAtNs = $nowNs + ($delaySeconds * 1_000_000_000);
        $this->log->recordNotice("[SCHEDULER] {$task->name} delayed by request governance cooldown {$delaySeconds}s", [
            'plugin' => $task->hook,
            'task' => 'scheduler.governance',
        ]);

        return true;
    }

    /**
     * 应用分组ConcurrencyBackoff
     * @param ScheduledTask $task
     * @param float $nowNs
     * @return bool
     */
    private function applyGroupConcurrencyBackoff(ScheduledTask $task, float $nowNs): bool
    {
        if ($task->governanceGroup === '' || $task->governanceGroupMaxConcurrency < 1) {
            return false;
        }

        $running = 0;
        foreach ($this->tasks as $registeredTask) {
            if ($registeredTask->governanceGroup !== $task->governanceGroup) {
                continue;
            }

            $running += $this->running[$registeredTask->hook] ?? 0;
        }

        if ($running < $task->governanceGroupMaxConcurrency) {
            return false;
        }

        $delaySeconds = $this->resolveGovernanceGroupBackoffSeconds($task);
        $task->nextRunAtNs = $nowNs + ($delaySeconds * 1_000_000_000);
        $this->log->recordNotice("[SCHEDULER] {$task->name} delayed by governance group {$task->governanceGroup}", [
            'plugin' => $task->hook,
            'task' => 'scheduler.governance_group',
        ]);

        return true;
    }

    /**
     * 解析治理分组BackoffSeconds
     * @param ScheduledTask $task
     * @return float
     */
    private function resolveGovernanceGroupBackoffSeconds(ScheduledTask $task): float
    {
        if ($task->governanceGroupBackoffSeconds > 0.0) {
            return $task->governanceGroupBackoffSeconds;
        }

        return match ($task->governanceProfile) {
            'auth' => 5.0,
            'interactive' => 2.0,
            default => 1.0,
        };
    }

    /**
     * 解析治理CooldownMultiplier
     * @param ScheduledTask $task
     * @return float
     */
    private function resolveGovernanceCooldownMultiplier(ScheduledTask $task): float
    {
        if ($task->governanceCooldownMultiplier > 0.0) {
            return $task->governanceCooldownMultiplier;
        }

        return match ($task->governanceProfile) {
            'auth' => 2.0,
            'interactive' => 1.5,
            default => 1.0,
        };
    }

    /**
     * 处理状态存储
     * @return SchedulerStateStore
     */
    private function stateStore(): SchedulerStateStore
    {
        return $this->stateStore ??= $this->schedulerStateStore;
    }

    /**
     * 判断Hold任务For登录待处理流程是否满足条件
     * @param ScheduledTask $task
     * @return bool
     */
    private function shouldHoldTaskForLoginPendingFlow(ScheduledTask $task): bool
    {
        if ($task->hook === 'Login') {
            return false;
        }

        if ($this->loginRecoveryRequested) {
            return true;
        }

        return $this->loginGateStateService()->shouldBlockBusinessTasks();
    }

    /**
     * 判断待处理登录流程是否满足条件
     * @return bool
     */
    private function hasPendingLoginFlow(): bool
    {
        return $this->loginGateStateService()->hasPendingFlow();
    }

    /**
     * 处理登录闸门状态服务
     * @return LoginGateStateService
     */
    private function loginGateStateService(): LoginGateStateService
    {
        if (!$this->loginGateStateService instanceof LoginGateStateService) {
            throw new InvalidArgumentException('Scheduler login gate state dependency is not configured.');
        }

        return $this->loginGateStateService;
    }

    /**
     * 处理登录手动Intervention策略
     * @return LoginManualInterventionPolicy
     */
    private function loginManualInterventionPolicy(): LoginManualInterventionPolicy
    {
        if (!$this->loginManualInterventionPolicy instanceof LoginManualInterventionPolicy) {
            throw new InvalidArgumentException('Scheduler login manual intervention dependency is not configured.');
        }

        return $this->loginManualInterventionPolicy;
    }

    /**
     * 请求登录恢复
     * @param ScheduledTask $sourceTask
     * @param float $nowNs
     * @param string $reason
     * @return void
     */
    private function requestLoginRecovery(ScheduledTask $sourceTask, float $nowNs, string $reason): void
    {
        $this->loginRecoveryRequested = true;
        $loginTask = $this->tasks['Login'] ?? null;
        if (!$loginTask instanceof ScheduledTask) {
            return;
        }

        if ($loginTask->nextRunAtNs > $nowNs) {
            $loginTask->nextRunAtNs = $nowNs;
            $this->persistTaskState($loginTask);
        }

        $this->log->recordNotice("[SCHEDULER] rearm Login because {$sourceTask->name} requires authentication: {$reason}", [
            'plugin' => $sourceTask->hook,
            'task' => 'scheduler.login_rearm',
        ]);
    }

    /**
     * 刷新登录恢复状态
     * @param ScheduledTask $task
     * @return void
     */
    private function refreshLoginRecoveryState(ScheduledTask $task): void
    {
        if ($task->hook !== 'Login') {
            return;
        }

        $this->loginRecoveryRequested = $this->loginGateStateService()->shouldBlockBusinessTasks();
    }

    /**
     * 恢复截止时间From状态
     * @param mixed $epochSeconds
     * @param float $nowNs
     * @param float $nowEpoch
     * @param float $default
     * @return float
     */
    private function restoreDeadlineFromState(
        mixed $epochSeconds,
        float $nowNs,
        float $nowEpoch,
        ?float $default = null,
    ): float {
        if (!is_numeric($epochSeconds)) {
            return $default ?? $nowNs;
        }

        $deadlineEpoch = (float)$epochSeconds;
        if ($deadlineEpoch <= $nowEpoch) {
            return $default ?? $nowNs;
        }

        return $nowNs + (($deadlineEpoch - $nowEpoch) * 1_000_000_000);
    }

    /**
     * 保存或更新任务状态
     * @param ScheduledTask $task
     * @return void
     */
    private function persistTaskState(ScheduledTask $task): void
    {
        $this->stateStore()->saveTaskState(
            $task->hook,
            $this->deadlineToEpochSeconds($task->nextRunAtNs),
            $task->failureCount,
            $this->deadlineToEpochSeconds($task->circuitOpenUntilNs),
        );
    }

    /**
     * 处理截止时间ToEpochSeconds
     * @param float $deadlineNs
     * @return float
     */
    private function deadlineToEpochSeconds(float $deadlineNs): float
    {
        if ($deadlineNs <= 0.0) {
            return 0.0;
        }

        $nowNs = $this->monotonicNowNs();
        $nowEpoch = $this->wallTimeNowSeconds();

        return $nowEpoch + max(0.0, ($deadlineNs - $nowNs) / 1_000_000_000);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePluginDefinition(mixed $plugin): array
    {
        if (!is_array($plugin)) {
            throw new InvalidArgumentException('Scheduler plugin definition must be an array.');
        }

        $missing = [];
        if (!isset($plugin['hook']) || trim((string)$plugin['hook']) === '') {
            $missing[] = 'hook';
        }
        if (!isset($plugin['name']) || trim((string)$plugin['name']) === '') {
            $missing[] = 'name';
        }

        $hasInterval = isset($plugin['interval_seconds']) && is_numeric($plugin['interval_seconds']);
        $hasCycle = isset($plugin['cycle']) && trim((string)$plugin['cycle']) !== '';
        if (!$hasInterval && !$hasCycle) {
            $missing[] = 'cycle';
        }

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Scheduler plugin definition is missing required metadata: ' . implode(', ', $missing)
            );
        }

        return $plugin;
    }
}
