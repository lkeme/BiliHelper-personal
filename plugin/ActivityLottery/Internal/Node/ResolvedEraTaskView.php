<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraTaskSnapshot;

final class ResolvedEraTaskView
{
    /**
     * @param array<string, mixed> $runtimeMap
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $progressMap
     */
    private function __construct(
        private readonly string $taskId,
        private readonly ?EraTaskSnapshot $task,
        private readonly array $runtimeMap,
        private readonly array $activity,
        private readonly array $progressMap,
    ) {
    }

    public static function fromFlowAndNode(ActivityFlow $flow, ActivityNode $node): self
    {
        $taskId = trim((string)($node->payload()['task_id'] ?? ''));
        $context = $flow->context()->toArray();
        $task = null;

        $tasks = $context['era_page_snapshot']['tasks'] ?? [];
        if (is_array($tasks)) {
            foreach ($tasks as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }

                $snapshot = EraTaskSnapshot::fromArray($candidate);
                if ($snapshot->taskId() === $taskId) {
                    $task = $snapshot;
                    break;
                }
            }
        }

        $runtimeMap = is_array($context['era_task_runtime'] ?? null)
            ? $context['era_task_runtime']
            : [];
        $progressMap = is_array($context['era_task_progress_snapshot'] ?? null)
            ? $context['era_task_progress_snapshot']
            : [];

        return new self($taskId, $task, $runtimeMap, $flow->activity(), $progressMap);
    }

    public function taskId(): string
    {
        return $this->taskId;
    }

    public function task(): ?EraTaskSnapshot
    {
        return $this->task;
    }

    /**
     * @return array<string, mixed>
     */
    public function activity(): array
    {
        return $this->activity;
    }

    /**
     * @return array<string, mixed>
     */
    public function taskRuntime(): array
    {
        if ($this->taskId === '') {
            return [];
        }

        $state = $this->runtimeMap[$this->taskId] ?? [];
        return is_array($state) ? $state : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function taskProgress(): array
    {
        if ($this->taskId === '') {
            return [];
        }

        $snapshot = $this->progressMap[$this->taskId] ?? [];
        return is_array($snapshot) ? $snapshot : [];
    }

    public function resolvedTaskStatus(): int
    {
        $progress = $this->taskProgress();
        if (isset($progress['task_status']) && is_numeric($progress['task_status'])) {
            return (int)$progress['task_status'];
        }

        return $this->task?->taskStatus() ?? 0;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function replaceTaskRuntime(array $state): array
    {
        if ($this->taskId === '') {
            return [];
        }

        $runtimeMap = $this->runtimeMap;
        if ($state === []) {
            unset($runtimeMap[$this->taskId]);
        } else {
            $runtimeMap[$this->taskId] = $state;
        }

        return ['era_task_runtime' => $runtimeMap];
    }

    /**
     * @param array<string, mixed> $patch
     * @param string[] $removeKeys
     * @return array<string, mixed>
     */
    public function patchTaskRuntime(array $patch, array $removeKeys = []): array
    {
        $state = array_replace($this->taskRuntime(), $patch);
        foreach ($removeKeys as $key) {
            unset($state[$key]);
        }

        return $this->replaceTaskRuntime($state);
    }
}
