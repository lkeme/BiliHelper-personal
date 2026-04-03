<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use Bhp\Plugin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\ActivityLottery\Internal\EraActivityTask;
use RuntimeException;

final class ActivityFlowPlanner
{
    /**
     * @return array<string, array{type: string, default_lane: string, allowed_lanes: array<int, string>, supported: bool, default_status: string}>
     */
    public static function capabilityContracts(): array
    {
        return [
            EraActivityTask::CAPABILITY_FOLLOW => [
                'type' => 'era_task_follow',
                'default_lane' => 'follow',
                'allowed_lanes' => ['follow'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            'unfollow' => [
                'type' => 'era_task_unfollow',
                'default_lane' => 'unfollow',
                'allowed_lanes' => ['unfollow'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            'task_status' => [
                'type' => 'era_task_status',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraActivityTask::CAPABILITY_SHARE => [
                'type' => 'era_task_share',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED => [
                'type' => 'era_task_watch_video_fixed',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status', 'watch_video'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC => [
                'type' => 'era_task_watch_video_topic',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status', 'watch_video'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraActivityTask::CAPABILITY_WATCH_LIVE => [
                'type' => 'era_task_watch_live',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status', 'watch_live_init', 'watch_live'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraActivityTask::CAPABILITY_MANUAL => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
            EraActivityTask::CAPABILITY_COIN_TOPIC => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
            EraActivityTask::CAPABILITY_LIKE_TOPIC => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
            EraActivityTask::CAPABILITY_COMMENT_TOPIC => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
            EraActivityTask::CAPABILITY_UNKNOWN => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
        ];
    }

    /**
     * @return array<string, array{default_lane: string, allowed_lanes: array<int, string>, default_status: string}>
     */
    public static function nodeTypeContracts(): array
    {
        $contracts = [
            'load_activity_snapshot' => ['default_lane' => 'page_fetch', 'allowed_lanes' => ['page_fetch'], 'default_status' => ActivityNodeStatus::PENDING],
            'validate_activity_window' => ['default_lane' => 'task_status', 'allowed_lanes' => ['task_status'], 'default_status' => ActivityNodeStatus::PENDING],
            'parse_era_page' => ['default_lane' => 'page_fetch', 'allowed_lanes' => ['page_fetch'], 'default_status' => ActivityNodeStatus::PENDING],
            'refresh_draw_times' => ['default_lane' => 'draw_refresh', 'allowed_lanes' => ['draw_refresh'], 'default_status' => ActivityNodeStatus::PENDING],
            'execute_draw' => ['default_lane' => 'draw_execute', 'allowed_lanes' => ['draw_execute'], 'default_status' => ActivityNodeStatus::PENDING],
            'claim_reward' => ['default_lane' => 'claim_reward', 'allowed_lanes' => ['claim_reward'], 'default_status' => ActivityNodeStatus::PENDING],
            'finalize_flow' => ['default_lane' => 'task_status', 'allowed_lanes' => ['task_status'], 'default_status' => ActivityNodeStatus::PENDING],
            'era_task_skipped' => ['default_lane' => 'task_status', 'allowed_lanes' => ['task_status'], 'default_status' => ActivityNodeStatus::SKIPPED],
        ];

        foreach (self::capabilityContracts() as $contract) {
            $contracts[$contract['type']] = [
                'default_lane' => $contract['default_lane'],
                'allowed_lanes' => $contract['allowed_lanes'],
                'default_status' => $contract['default_status'],
            ];
        }

        return $contracts;
    }

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
        return array_map(fn (string $type): ActivityNode => $this->nodeByContract($type), [
            'load_activity_snapshot',
            'validate_activity_window',
            'parse_era_page',
        ]);
    }

    /**
     * @return ActivityNode[]
     */
    private function fixedTailNodes(): array
    {
        return array_map(fn (string $type): ActivityNode => $this->nodeByContract($type), [
            'refresh_draw_times',
            'execute_draw',
            'claim_reward',
            'finalize_flow',
        ]);
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
            if ($normalizedTask['task_id'] === '') {
                $nodes[] = new ActivityNode(
                    'era_task_skipped',
                    [
                        'lane' => $this->defaultLaneForNodeType('era_task_skipped'),
                        'capability' => $normalizedTask['capability'],
                        'reason' => 'missing_task_id',
                    ],
                    ActivityNodeStatus::SKIPPED,
                );
                continue;
            }

            $capability = $normalizedTask['capability'];
            $mapped = $this->mapCapability($capability);
            if ($mapped === null) {
                $nodes[] = new ActivityNode(
                    'era_task_skipped',
                    [
                        'lane' => $this->defaultLaneForNodeType('era_task_skipped'),
                        'task_id' => $normalizedTask['task_id'],
                        'capability' => $capability,
                        'reason' => sprintf('unsupported capability: %s', $capability === '' ? '(empty)' : $capability),
                    ],
                    ActivityNodeStatus::SKIPPED,
                );
                continue;
            }

            $nodes[] = new ActivityNode(
                $mapped['type'],
                [
                    'lane' => $mapped['lane'],
                    'task_id' => $normalizedTask['task_id'],
                    'capability' => $capability,
                ],
            );
        }

        return $nodes;
    }

    /**
     * @return array{type: string, lane: string}|null
     */
    private function mapCapability(string $capability): ?array
    {
        $contract = self::capabilityContracts()[$capability] ?? null;
        if ($contract === null || $contract['supported'] === false) {
            return null;
        }

        return ['type' => $contract['type'], 'lane' => $contract['default_lane']];
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
     * @return array{task_id: string, capability: string}
     */
    private function normalizeTask(mixed $task): array
    {
        if (is_array($task)) {
            return [
                'task_id' => $this->normalizeArrayTaskField($task, ['task_id', 'taskId'], 'task_id'),
                'capability' => $this->normalizeArrayTaskField($task, ['capability'], 'capability'),
            ];
        }

        if ($task instanceof EraActivityTask) {
            return [
                'task_id' => trim($task->taskId),
                'capability' => trim($task->capability),
            ];
        }

        throw new RuntimeException(sprintf('非法任务类型: %s', get_debug_type($task)));
    }

    /**
     * @param array<string, mixed> $task
     * @param string[] $keys
     */
    private function normalizeArrayTaskField(array $task, array $keys, string $fieldName): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $task)) {
                continue;
            }

            $value = $task[$key];
            if ($value === null) {
                return '';
            }
            if (!is_scalar($value)) {
                throw new RuntimeException(sprintf(
                    '非法任务字段类型: %s=%s',
                    $fieldName,
                    get_debug_type($value),
                ));
            }

            return trim((string)$value);
        }

        return '';
    }

    private function nodeByContract(string $type): ActivityNode
    {
        $contracts = self::nodeTypeContracts();
        if (!isset($contracts[$type])) {
            throw new RuntimeException(sprintf('未知 node type 契约: %s', $type));
        }

        $contract = $contracts[$type];
        return new ActivityNode($type, ['lane' => $contract['default_lane']], $contract['default_status']);
    }

    private function defaultLaneForNodeType(string $type): string
    {
        $contracts = self::nodeTypeContracts();
        if (!isset($contracts[$type])) {
            throw new RuntimeException(sprintf('未知 node type 契约: %s', $type));
        }

        return $contracts[$type]['default_lane'];
    }
}
