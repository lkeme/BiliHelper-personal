<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\ActivityCatalogItem;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraPageSnapshot;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskCapabilityResolver;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot;
use RuntimeException;

final class ActivityFlowPlanner
{
    /**
     * capability 越小优先级越高；未识别能力统一放到最后。
     *
     * @var array<string, int>
     */
    private const DYNAMIC_CAPABILITY_PRIORITY = [
        EraTaskCapabilityResolver::CAPABILITY_CLAIM_REWARD => 100,
        EraTaskCapabilityResolver::CAPABILITY_SHARE => 200,
        EraTaskCapabilityResolver::CAPABILITY_FOLLOW => 300,
        'unfollow' => 350,
        EraTaskCapabilityResolver::CAPABILITY_WATCH_VIDEO_FIXED => 400,
        EraTaskCapabilityResolver::CAPABILITY_WATCH_VIDEO_TOPIC => 400,
        EraTaskCapabilityResolver::CAPABILITY_WATCH_LIVE => 500,
    ];

    /**
     * @return array<string, array{type: string, default_lane: string, allowed_lanes: array<int, string>, supported: bool, default_status: string}>
     */
    public static function capabilityContracts(): array
    {
        return [
            EraTaskCapabilityResolver::CAPABILITY_CLAIM_REWARD => [
                'type' => 'era_task_claim_reward',
                'default_lane' => 'claim_reward',
                'allowed_lanes' => ['claim_reward'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraTaskCapabilityResolver::CAPABILITY_FOLLOW => [
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
            EraTaskCapabilityResolver::CAPABILITY_SHARE => [
                'type' => 'era_task_share',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraTaskCapabilityResolver::CAPABILITY_WATCH_VIDEO_FIXED => [
                'type' => 'era_task_watch_video_fixed',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status', 'watch_video'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraTaskCapabilityResolver::CAPABILITY_WATCH_VIDEO_TOPIC => [
                'type' => 'era_task_watch_video_topic',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status', 'watch_video'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraTaskCapabilityResolver::CAPABILITY_WATCH_LIVE => [
                'type' => 'era_task_watch_live',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status', 'watch_live_init', 'watch_live'],
                'supported' => true,
                'default_status' => ActivityNodeStatus::PENDING,
            ],
            EraTaskCapabilityResolver::CAPABILITY_MANUAL => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
            EraTaskCapabilityResolver::CAPABILITY_COIN_TOPIC => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
            EraTaskCapabilityResolver::CAPABILITY_LIKE_TOPIC => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
            EraTaskCapabilityResolver::CAPABILITY_COMMENT_TOPIC => [
                'type' => 'era_task_skipped',
                'default_lane' => 'task_status',
                'allowed_lanes' => ['task_status'],
                'supported' => false,
                'default_status' => ActivityNodeStatus::SKIPPED,
            ],
            EraTaskCapabilityResolver::CAPABILITY_UNKNOWN => [
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
            'record_draw_result' => ['default_lane' => 'task_status', 'allowed_lanes' => ['task_status'], 'default_status' => ActivityNodeStatus::PENDING],
            'notify_draw_result' => ['default_lane' => 'task_status', 'allowed_lanes' => ['task_status'], 'default_status' => ActivityNodeStatus::PENDING],
            'final_claim_reward' => ['default_lane' => 'claim_reward', 'allowed_lanes' => ['claim_reward'], 'default_status' => ActivityNodeStatus::PENDING],
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

    public function plan(ActivityCatalogItem $item, ?EraPageSnapshot $pageSnapshot, string $bizDate): ActivityFlow
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
        $nodes = array_map(fn (string $type): ActivityNode => $this->nodeByContract($type), [
            'refresh_draw_times',
            'execute_draw',
            'record_draw_result',
            'notify_draw_result',
            'final_claim_reward',
        ]);
        $nodes[] = new ActivityNode('era_task_unfollow', [
            'lane' => $this->defaultLaneForNodeType('era_task_unfollow'),
            'cleanup_scope' => 'temporary_follow_uids',
        ]);
        $nodes[] = $this->nodeByContract('finalize_flow');

        return $nodes;
    }

    /**
     * @return ActivityNode[]
     */
    private function dynamicEraNodes(?EraPageSnapshot $pageSnapshot): array
    {
        if ($pageSnapshot === null) {
            return [];
        }

        $sortableTasks = [];
        foreach ($pageSnapshot->tasks() as $index => $task) {
            $normalizedTask = $this->normalizeTask($task);
            $sortableTasks[] = [
                'task' => $normalizedTask,
                'index' => (int)$index,
                'priority' => $this->dynamicTaskPriority($normalizedTask['capability']),
            ];
        }
        usort($sortableTasks, static function (array $left, array $right): int {
            return [
                $left['priority'],
                $left['task']['task_id'],
                $left['task']['capability'],
                $left['index'],
            ] <=> [
                $right['priority'],
                $right['task']['task_id'],
                $right['task']['capability'],
                $right['index'],
            ];
        });

        $nodes = [];
        foreach ($sortableTasks as $item) {
            /** @var array{task_id: string, capability: string} $normalizedTask */
            $normalizedTask = $item['task'];
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
     * @return array{task_id: string, capability: string}
     */
    private function normalizeTask(EraTaskSnapshot $task): array
    {
        return [
            'task_id' => trim($task->taskId()),
            'capability' => trim($task->capability()),
        ];
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

    private function dynamicTaskPriority(string $capability): int
    {
        return self::DYNAMIC_CAPABILITY_PRIORITY[$capability] ?? 1_000;
    }
}


