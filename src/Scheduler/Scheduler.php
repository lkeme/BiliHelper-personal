<?php declare(strict_types=1);

namespace Bhp\Scheduler;

use Bhp\Http\HttpRequestTrafficMonitor;
use Bhp\Log\Log;
use Bhp\Plugin\Plugin;
use Bhp\Util\DesignPattern\SingleTon;
use Bhp\Util\Exceptions\NoLoginException;
use Revolt\EventLoop;
use Throwable;
use function Amp\async;

class Scheduler extends SingleTon
{
    private const CIRCUIT_FAILURE_THRESHOLD = 3;
    private const CIRCUIT_OPEN_SECONDS = 300.0;

    /** @var array<string, ScheduledTask> */
    private array $tasks = [];

    /** @var array<string, int> */
    private array $running = [];

    private bool $started = false;
    private ?SchedulerStateStore $stateStore = null;

    public function init(): void
    {
    }

    public function registerPlugins(array $plugins): void
    {
        $now = $this->monotonicNowNs();
        $nowEpoch = $this->wallTimeNowSeconds();
        $states = $this->stateStore()->load();
        foreach ($plugins as $plugin) {
            $hook = (string)$plugin['hook'];
            $intervalSeconds = isset($plugin['interval_seconds']) && is_numeric($plugin['interval_seconds'])
                ? max(0.05, (float)$plugin['interval_seconds'])
                : $this->parseCycleToSeconds((string)$plugin['cycle']);
            $taskState = $states[$hook] ?? null;

            $this->tasks[$hook] = new ScheduledTask(
                $hook,
                (string)$plugin['name'],
                (int)($plugin['priority'] ?? 9999),
                $intervalSeconds,
                (string)($plugin['overrun_policy'] ?? TaskPolicy::SKIP),
                max(1, (int)($plugin['max_concurrency'] ?? 1)),
                max(0.1, (float)($plugin['timeout_seconds'] ?? 30.0)),
                $this->restoreDeadlineFromState($taskState['next_run_at'] ?? null, $now, $nowEpoch),
                $intervalSeconds < 1.0,
                (bool)($plugin['bootstrap_first'] ?? false),
                array_values(array_filter($plugin['governance_hosts'] ?? [], 'is_string')),
                max(0, (int)($plugin['governance_window_seconds'] ?? 0)),
                max(0, (int)($plugin['governance_max_requests_per_host'] ?? 0)),
                max(0, (int)($plugin['governance_cooldown_seconds'] ?? 0)),
                (string)($plugin['governance_group'] ?? ''),
                max(0, (int)($plugin['governance_group_max_concurrency'] ?? 0)),
                (string)($plugin['governance_profile'] ?? ''),
                max(0.0, (float)($plugin['governance_group_backoff_seconds'] ?? 0.0)),
                max(0.0, (float)($plugin['governance_cooldown_multiplier'] ?? 0.0)),
                isset($taskState['failure_count']) ? max(0, (int)$taskState['failure_count']) : 0,
                $this->restoreDeadlineFromState($taskState['circuit_open_until'] ?? null, $now, $nowEpoch, 0.0),
            );
        }
    }

    public function run(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->runInitialRound();
        EventLoop::repeat(0.05, fn () => $this->tick(true));
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

    private function runInitialRound(): void
    {
        $tasks = array_values($this->tasks);
        usort($tasks, function (ScheduledTask $left, ScheduledTask $right): int {
            if ($left->bootstrapFirst !== $right->bootstrapFirst) {
                return $left->bootstrapFirst ? -1 : 1;
            }

            return $left->priority <=> $right->priority;
        });

        $now = $this->monotonicNowNs();
        foreach ($tasks as $task) {
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

    private function tick(bool $highFrequency): void
    {
        $now = $this->monotonicNowNs();
        foreach ($this->tasks as $task) {
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

    private function dispatch(ScheduledTask $task, float $nowNs): void
    {
        $running = $this->running[$task->hook] ?? 0;
        if ($running >= $task->maxConcurrency) {
            if ($task->policy === TaskPolicy::SKIP) {
                Log::debug("[SCHEDULER] skip {$task->name} because previous execution is still running", [
                    'plugin' => $task->hook,
                    'task' => 'scheduler.dispatch',
                ]);
                $this->advanceNextRunAt($task, $nowNs);
            }
            return;
        }

        $task->nextRunAtNs = PHP_INT_MAX;
        $this->running[$task->hook] = $running + 1;

        async(function () use ($task) {
            $startedAt = $this->monotonicNowNs();
            $result = TaskResult::keepSchedule();
            try {
                Log::debug("[SCHEDULER] dispatch {$task->name} interval={$task->intervalSeconds}s", [
                    'plugin' => $task->hook,
                    'task' => 'scheduler.dispatch',
                ]);
                $result = Log::withContext([
                    'plugin' => $task->hook,
                    'task' => 'plugin.run',
                ], fn () => Plugin::getInstance()->runTask($task->hook));
            } catch (NoLoginException $e) {
                Log::warning("[SCHEDULER] {$task->name} login required: {$e->getMessage()}", [
                    'plugin' => $task->hook,
                    'task' => 'plugin.run',
                ]);
                $result = TaskResult::after(3600);
            } catch (Throwable $e) {
                Log::error("[SCHEDULER] {$task->name} failed: {$e->getMessage()}", [
                    'plugin' => $task->hook,
                    'task' => 'plugin.run',
                ]);
            } finally {
                $finishedAt = $this->monotonicNowNs();
                $durationSeconds = ($finishedAt - $startedAt) / 1_000_000_000;
                if ($durationSeconds > $task->timeoutSeconds) {
                    Log::warning("[SCHEDULER] {$task->name} timeout exceeded {$durationSeconds}s > {$task->timeoutSeconds}s", [
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

    private function dispatchSync(ScheduledTask $task, float $nowNs): TaskResult
    {
        Log::debug("[SCHEDULER.INIT] dispatch {$task->name} priority={$task->priority}", [
            'plugin' => $task->hook,
            'task' => 'scheduler.init',
        ]);

        $startedAt = $this->monotonicNowNs();
        $result = TaskResult::keepSchedule();
        try {
            $result = Log::withContext([
                'plugin' => $task->hook,
                'task' => 'plugin.run',
            ], fn () => Plugin::getInstance()->runTask($task->hook));
        } catch (NoLoginException $e) {
            Log::warning("[SCHEDULER.INIT] {$task->name} login required: {$e->getMessage()}", [
                'plugin' => $task->hook,
                'task' => 'plugin.run',
            ]);
            $result = TaskResult::after(3600);
        } catch (Throwable $e) {
            Log::error("[SCHEDULER.INIT] {$task->name} failed: {$e->getMessage()}", [
                'plugin' => $task->hook,
                'task' => 'plugin.run',
            ]);
        } finally {
            $durationSeconds = ($this->monotonicNowNs() - $startedAt) / 1_000_000_000;
            if ($durationSeconds > $task->timeoutSeconds) {
                Log::warning("[SCHEDULER.INIT] {$task->name} timeout exceeded {$durationSeconds}s > {$task->timeoutSeconds}s", [
                    'plugin' => $task->hook,
                    'task' => 'scheduler.init',
                ]);
            }
        }

        $this->recordTaskOutcome($task, $result, $this->monotonicNowNs());

        return $result;
    }

    private function applyTaskResult(ScheduledTask $task, TaskResult $result, float $referenceNs): void
    {
        if ($result->nextRunAfterSeconds !== null) {
            $delayNs = max(1.0, round(max(0.0, $result->nextRunAfterSeconds) * 1_000_000_000));
            $task->nextRunAtNs = $referenceNs + $delayNs;
            $this->persistTaskState($task);
            return;
        }

        $this->advanceNextRunAt($task, $referenceNs);
        $this->persistTaskState($task);
    }

    private function advanceNextRunAt(ScheduledTask $task, float $nowNs): void
    {
        $intervalNs = max(1.0, round($task->intervalSeconds * 1_000_000_000));
        if ($intervalNs < 1.0) {
            $intervalNs = 1.0;
        }

        if ($task->nextRunAtNs === 0.0) {
            $task->nextRunAtNs = $nowNs + $intervalNs;
            return;
        }

        do {
            $task->nextRunAtNs += $intervalNs;
        } while ($task->nextRunAtNs <= $nowNs);
    }

    private function monotonicNowNs(): float
    {
        return (float) hrtime(true);
    }

    private function wallTimeNowSeconds(): float
    {
        return microtime(true);
    }

    private function formatNextRunDelay(ScheduledTask $task, float $nowNs): string
    {
        if ($task->nextRunAtNs >= (float)PHP_INT_MAX) {
            return 'running';
        }

        return (string)round(max(0.0, ($task->nextRunAtNs - $nowNs) / 1_000_000_000), 2);
    }

    private function parseCycleToSeconds(string $cycle): float
    {
        if ($cycle === '') {
            return 60.0;
        }

        preg_match('/(\d+)(?:-(\d+))?/', $cycle, $matches);
        $value = isset($matches[1]) ? (int)$matches[1] : 1;

        return match (true) {
            str_contains($cycle, '秒') => max(0.05, (float)$value),
            str_contains($cycle, '分钟') => max(1.0, (float)$value * 60),
            str_contains($cycle, '小时') => max(1.0, (float)$value * 3600),
            default => max(1.0, (float)$value * 60),
        };
    }

    private function isCircuitOpen(ScheduledTask $task, float $nowNs): bool
    {
        if ($task->circuitOpenUntilNs <= $nowNs) {
            if ($task->circuitOpenUntilNs > 0.0) {
                Log::notice("[SCHEDULER] {$task->name} circuit closed, resume scheduling", [
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

        Log::warning("[SCHEDULER] {$task->name} circuit opened for " . self::CIRCUIT_OPEN_SECONDS . "s", [
            'plugin' => $task->hook,
            'task' => 'scheduler.circuit',
        ]);
    }

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
            $hostRemaining = HttpRequestTrafficMonitor::getInstance()->cooldownRemaining(
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
        Log::notice("[SCHEDULER] {$task->name} delayed by request governance cooldown {$delaySeconds}s", [
            'plugin' => $task->hook,
            'task' => 'scheduler.governance',
        ]);

        return true;
    }

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
        Log::notice("[SCHEDULER] {$task->name} delayed by governance group {$task->governanceGroup}", [
            'plugin' => $task->hook,
            'task' => 'scheduler.governance_group',
        ]);

        return true;
    }

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

    private function stateStore(): SchedulerStateStore
    {
        return $this->stateStore ??= new SchedulerStateStore();
    }

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

    private function persistTaskState(ScheduledTask $task): void
    {
        $this->stateStore()->saveTaskState(
            $task->hook,
            $this->deadlineToEpochSeconds($task->nextRunAtNs),
            $task->failureCount,
            $this->deadlineToEpochSeconds($task->circuitOpenUntilNs),
        );
    }

    private function deadlineToEpochSeconds(float $deadlineNs): float
    {
        if ($deadlineNs <= 0.0) {
            return 0.0;
        }

        $nowNs = $this->monotonicNowNs();
        $nowEpoch = $this->wallTimeNowSeconds();

        return $nowEpoch + max(0.0, ($deadlineNs - $nowNs) / 1_000_000_000);
    }
}
