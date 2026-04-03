<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\EraActivityTask;

final class ActivityFlowPlanner
{
    public function plan(ActivityCatalogItem $item, mixed $pageSnapshot, string $bizDate): ActivityFlow
    {
        $nodes = array_merge(
            $this->fixedPrefixNodes(),
            $this->dynamicEraNodes($pageSnapshot),
            $this->fixedTailNodes(),
        );

        return ActivityFlowFactory::create($item, $bizDate, $nodes);
    }

    /**
     * @return ActivityNode[]
     */
    private function fixedPrefixNodes(): array
    {
        return [
            new ActivityNode('load_activity_snapshot', ['lane' => 'page_fetch']),
            new ActivityNode('validate_activity_window', ['lane' => 'task_status']),
            new ActivityNode('parse_era_page', ['lane' => 'page_fetch']),
        ];
    }

    /**
     * @return ActivityNode[]
     */
    private function fixedTailNodes(): array
    {
        return [
            new ActivityNode('refresh_draw_times', ['lane' => 'draw_refresh']),
            new ActivityNode('execute_draw', ['lane' => 'draw_execute']),
            new ActivityNode('claim_reward', ['lane' => 'claim_reward']),
            new ActivityNode('finalize_flow', ['lane' => 'task_status']),
        ];
    }

    /**
     * @return ActivityNode[]
     */
    private function dynamicEraNodes(mixed $pageSnapshot): array
    {
        $tasks = $this->extractTasks($pageSnapshot);
        $nodes = [];
        foreach ($tasks as $task) {
            $normalizedTask = $this->normalizeTask($task);
            if ($normalizedTask === null) {
                continue;
            }

            $capability = $normalizedTask['capability'];
            $mapped = $this->mapCapability($capability);
            if ($mapped === null) {
                continue;
            }

            $nodes[] = new ActivityNode(
                $mapped['type'],
                [
                    'lane' => $mapped['lane'],
                    'task_id' => $normalizedTask['task_id'],
                    'capability' => $capability,
                    'task_status' => $normalizedTask['task_status'],
                ],
            );
        }

        return $nodes;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function mapTable(): array
    {
        return [
            ['capability' => 'follow', 'type' => 'era_task_follow', 'lane' => 'follow'],
            ['capability' => 'unfollow', 'type' => 'era_task_unfollow', 'lane' => 'unfollow'],
            ['capability' => 'task_status', 'type' => 'era_task_status', 'lane' => 'task_status'],
            ['capability' => 'share', 'type' => 'era_task_share', 'lane' => 'task_status'],
        ];
    }

    /**
     * @return array{type: string, lane: string}|null
     */
    private function mapCapability(string $capability): ?array
    {
        foreach ($this->mapTable() as $item) {
            if ($item['capability'] === $capability) {
                return ['type' => $item['type'], 'lane' => $item['lane']];
            }
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function extractTasks(mixed $pageSnapshot): array
    {
        if ($pageSnapshot === null) {
            return [];
        }

        if (is_array($pageSnapshot) && is_array($pageSnapshot['tasks'] ?? null)) {
            return array_values($pageSnapshot['tasks']);
        }

        if (is_object($pageSnapshot) && is_array($pageSnapshot->tasks ?? null)) {
            return array_values($pageSnapshot->tasks);
        }

        return [];
    }

    /**
     * @return array{task_id: string, capability: string, task_status: int}|null
     */
    private function normalizeTask(mixed $task): ?array
    {
        if (is_array($task)) {
            return [
                'task_id' => trim((string)($task['task_id'] ?? $task['taskId'] ?? '')),
                'capability' => trim((string)($task['capability'] ?? '')),
                'task_status' => (int)($task['task_status'] ?? $task['taskStatus'] ?? 0),
            ];
        }

        if ($task instanceof EraActivityTask) {
            return [
                'task_id' => trim($task->taskId),
                'capability' => trim($task->capability),
                'task_status' => (int)$task->taskStatus,
            ];
        }

        if (is_object($task)) {
            return [
                'task_id' => trim((string)($task->task_id ?? $task->taskId ?? '')),
                'capability' => trim((string)($task->capability ?? '')),
                'task_status' => (int)($task->task_status ?? $task->taskStatus ?? 0),
            ];
        }

        return null;
    }
}
