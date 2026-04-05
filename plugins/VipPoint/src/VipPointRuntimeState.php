<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\VipPoint;

final class VipPointRuntimeState
{
    /**
     * @param array<string, array<string, mixed>> $tasks
     * @param array<int|string, string> $targetTasks
     */
    public static function bootstrap(array $tasks, string $date, array $targetTasks): self
    {
        if (!isset($tasks[$date])) {
            $tasks[$date] = [
                'start' => true,
                'DelayedAction' => null,
            ];

            foreach ($targetTasks as $target => $label) {
                $taskKey = is_string($target) ? $target : $label;
                $tasks[$date][$taskKey] = false;
            }
        }

        return new self($tasks, $date);
    }

    /**
     * @param array<string, array<string, mixed>> $tasks
     */
    public function __construct(
        private array $tasks,
        private readonly string $date,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->tasks;
    }

    public function setTask(string $key, mixed $value): void
    {
        $this->tasks[$this->date][$key] = $value;
    }

    public function task(string $key): mixed
    {
        return $this->tasks[$this->date][$key] ?? null;
    }

    public function deleteTask(string $key): void
    {
        unset($this->tasks[$this->date][$key]);
    }

    /**
     * @param array<string, mixed> $action
     */
    public function setDelayedAction(array $action): void
    {
        $this->setTask('DelayedAction', $action);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function delayedAction(): ?array
    {
        $action = $this->task('DelayedAction');

        return is_array($action) ? $action : null;
    }

    public function clearDelayedAction(): void
    {
        $this->setTask('DelayedAction', null);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function queueDelayedAction(string $taskCode, string $type, array $context, int $delaySeconds): void
    {
        $this->setDelayedAction([
            'task_code' => $taskCode,
            'type' => $type,
            'context' => $context,
            'delay_seconds' => $delaySeconds,
        ]);
    }

    public function matchesDelayedAction(string $taskCode, string $type): bool
    {
        $action = $this->delayedAction();
        if ($action === null) {
            return false;
        }

        return ($action['task_code'] ?? null) === $taskCode
            && ($action['type'] ?? null) === $type;
    }
}
