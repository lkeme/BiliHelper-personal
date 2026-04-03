<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use RuntimeException;

final class ActivityFlow
{
    private readonly string $flowId;
    private readonly string $bizDate;
    private readonly string $status;
    /**
     * @var array<string, mixed>
     */
    private readonly array $activity;
    /**
     * @var ActivityNode[]
     */
    private readonly array $nodes;
    /**
     * @var array<int, array<string, mixed>>
     */
    private readonly array $logs;

    /**
     * @param array<string, mixed> $activity
     * @param ActivityNode[] $nodes
     * @param array<int, array<string, mixed>> $logs
     */
    public function __construct(
        string $flowId,
        string $bizDate,
        array $activity,
        string $status,
        private readonly int $currentNodeIndex,
        array $nodes,
        private readonly int $nextRunAt,
        private readonly int $attempts,
        private readonly ActivityFlowContext $context,
        array $logs,
        private readonly int $createdAt,
        private readonly int $updatedAt,
    ) {
        $this->flowId = trim($flowId);
        $this->bizDate = trim($bizDate);
        $this->activity = $activity;
        $this->status = trim($status);
        $this->nodes = $nodes;
        $this->logs = $logs;

        self::assertValid(
            $this->flowId,
            $this->bizDate,
            $this->status,
            $this->currentNodeIndex,
            $this->nodes,
            $this->logs,
            $this->attempts,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawActivity = $data['activity'] ?? [];
        if (!is_array($rawActivity)) {
            throw new RuntimeException('ActivityFlow activity 必须为数组');
        }

        $rawNodes = $data['nodes'] ?? [];
        if (!is_array($rawNodes)) {
            throw new RuntimeException('ActivityFlow nodes 必须为数组');
        }

        $nodes = [];
        foreach ($rawNodes as $index => $node) {
            if (!is_array($node)) {
                throw new RuntimeException(sprintf(
                    'ActivityFlow nodes[%d] 必须为数组',
                    (int)$index,
                ));
            }
            $nodes[] = ActivityNode::fromArray($node);
        }

        $rawContext = $data['context'] ?? [];
        if (!is_array($rawContext)) {
            throw new RuntimeException('ActivityFlow context 必须为数组');
        }
        $context = ActivityFlowContext::fromArray($rawContext);

        $rawLogs = $data['logs'] ?? [];
        if (!is_array($rawLogs)) {
            throw new RuntimeException('ActivityFlow logs 必须为数组');
        }

        $logs = [];
        foreach ($rawLogs as $index => $log) {
            if (!is_array($log)) {
                throw new RuntimeException(sprintf(
                    'ActivityFlow logs[%d] 必须为数组',
                    (int)$index,
                ));
            }
            $logs[] = $log;
        }

        return new self(
            (string)($data['flow_id'] ?? ''),
            (string)($data['biz_date'] ?? ''),
            $rawActivity,
            (string)($data['status'] ?? ActivityFlowStatus::PENDING),
            (int)($data['current_node_index'] ?? 0),
            $nodes,
            (int)($data['next_run_at'] ?? 0),
            (int)($data['attempts'] ?? 0),
            $context,
            $logs,
            (int)($data['created_at'] ?? 0),
            (int)($data['updated_at'] ?? 0),
        );
    }

    public function id(): string
    {
        return $this->flowId;
    }

    public function bizDate(): string
    {
        return $this->bizDate;
    }

    /**
     * @return array<string, mixed>
     */
    public function activity(): array
    {
        return $this->activity;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function currentNodeIndex(): int
    {
        return $this->currentNodeIndex;
    }

    /**
     * @return ActivityNode[]
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function nextRunAt(): int
    {
        return $this->nextRunAt;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function context(): ActivityFlowContext
    {
        return $this->context;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function logs(): array
    {
        return $this->logs;
    }

    public function createdAt(): int
    {
        return $this->createdAt;
    }

    public function updatedAt(): int
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'flow_id' => $this->flowId,
            'biz_date' => $this->bizDate,
            'activity' => $this->activity,
            'status' => $this->status,
            'current_node_index' => $this->currentNodeIndex,
            'nodes' => array_map(
                static fn (ActivityNode $node): array => $node->toArray(),
                $this->nodes,
            ),
            'next_run_at' => $this->nextRunAt,
            'attempts' => $this->attempts,
            'context' => $this->context->toArray(),
            'logs' => $this->logs,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * @return string[]
     */
    private static function allowedStatuses(): array
    {
        return [
            ActivityFlowStatus::PENDING,
            ActivityFlowStatus::RUNNING,
            ActivityFlowStatus::BLOCKED,
            ActivityFlowStatus::COMPLETED,
            ActivityFlowStatus::SKIPPED,
            ActivityFlowStatus::EXPIRED,
            ActivityFlowStatus::FAILED,
        ];
    }

    /**
     * @param ActivityNode[] $nodes
     */
    private static function assertValid(
        string $flowId,
        string $bizDate,
        string $status,
        int $currentNodeIndex,
        array $nodes,
        array $logs,
        int $attempts,
    ): void {
        if (trim($flowId) === '') {
            throw new RuntimeException('ActivityFlow flow_id 不能为空');
        }
        if (trim($bizDate) === '') {
            throw new RuntimeException('ActivityFlow biz_date 不能为空');
        }
        if (!in_array($status, self::allowedStatuses(), true)) {
            throw new RuntimeException('ActivityFlow 状态非法: ' . $status);
        }
        if ($attempts < 0) {
            throw new RuntimeException('ActivityFlow attempts 不能为负数');
        }
        if ($currentNodeIndex < 0) {
            throw new RuntimeException('ActivityFlow current_node_index 不能为负数');
        }
        if ($nodes === [] && $currentNodeIndex !== 0) {
            throw new RuntimeException('ActivityFlow nodes 为空时 current_node_index 必须为 0');
        }
        foreach ($nodes as $index => $node) {
            if (!$node instanceof ActivityNode) {
                throw new RuntimeException(sprintf(
                    'ActivityFlow nodes[%d] 必须为 ActivityNode',
                    (int)$index,
                ));
            }
        }
        if ($nodes !== [] && $currentNodeIndex >= count($nodes)) {
            throw new RuntimeException('ActivityFlow current_node_index 越界');
        }
        foreach ($logs as $index => $log) {
            if (!is_array($log)) {
                throw new RuntimeException(sprintf(
                    'ActivityFlow logs[%d] 必须为数组',
                    (int)$index,
                ));
            }
        }
    }
}
