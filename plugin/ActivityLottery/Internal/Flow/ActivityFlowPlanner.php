<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;

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
            if (!is_array($task)) {
                continue;
            }

            $capability = trim((string)($task['capability'] ?? ''));
            $mapped = $this->mapCapability($capability);
            if ($mapped === null) {
                continue;
            }

            $nodes[] = new ActivityNode(
                $mapped['type'],
                [
                    'lane' => $mapped['lane'],
                    'task_id' => trim((string)($task['task_id'] ?? '')),
                    'capability' => $capability,
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
}

