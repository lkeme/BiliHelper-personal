<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use RuntimeException;

final class ActivityFlow
{
    /**
     * @param array<string, mixed> $activity
     * @param ActivityNode[] $nodes
     * @param array<int, array<string, mixed>> $logs
     */
    public function __construct(
        private readonly string $flowId,
        private readonly string $bizDate,
        private readonly array $activity,
        private readonly string $status,
        private readonly int $currentNodeIndex,
        private readonly array $nodes,
        private readonly int $nextRunAt,
        private readonly int $attempts,
        private readonly ActivityFlowContext $context,
        private readonly array $logs,
        private readonly int $createdAt,
        private readonly int $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $bizDate = trim((string)($data['biz_date'] ?? ''));
        if ($bizDate === '') {
            throw new RuntimeException('ActivityFlow biz_date 不能为空');
        }

        $status = trim((string)($data['status'] ?? ActivityFlowStatus::PENDING));
        if (!in_array($status, self::allowedStatuses(), true)) {
            throw new RuntimeException('ActivityFlow 状态非法: ' . $status);
        }

        $nodes = [];
        foreach (($data['nodes'] ?? []) as $node) {
            if (is_array($node)) {
                $nodes[] = ActivityNode::fromArray($node);
            }
        }

        $currentNodeIndex = (int)($data['current_node_index'] ?? 0);
        if ($currentNodeIndex < 0) {
            throw new RuntimeException('ActivityFlow current_node_index 不能为负数');
        }
        if ($nodes !== [] && $currentNodeIndex >= count($nodes)) {
            throw new RuntimeException('ActivityFlow current_node_index 越界');
        }

        $attempts = (int)($data['attempts'] ?? 0);
        if ($attempts < 0) {
            throw new RuntimeException('ActivityFlow attempts 不能为负数');
        }

        $context = ActivityFlowContext::fromArray(
            is_array($data['context'] ?? null) ? $data['context'] : []
        );

        return new self(
            trim((string)($data['flow_id'] ?? '')),
            $bizDate,
            is_array($data['activity'] ?? null) ? $data['activity'] : [],
            $status,
            $currentNodeIndex,
            $nodes,
            (int)($data['next_run_at'] ?? 0),
            $attempts,
            $context,
            is_array($data['logs'] ?? null) ? $data['logs'] : [],
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
}
