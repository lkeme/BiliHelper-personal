<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use DateTimeImmutable;
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
        $this->bizDate = self::normalizeBizDate($bizDate);
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
        $rawActivity = self::readRequiredArrayField($data, 'activity');

        $rawNodes = self::readRequiredArrayField($data, 'nodes');
        self::assertSequentialList($rawNodes, 'nodes');

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

        $rawContext = self::readRequiredArrayField($data, 'context');
        $context = ActivityFlowContext::fromArray($rawContext);

        $rawLogs = self::readRequiredArrayField($data, 'logs');
        self::assertSequentialList($rawLogs, 'logs');

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
            self::readRequiredStringField($data, 'flow_id'),
            self::readRequiredStringField($data, 'biz_date'),
            $rawActivity,
            self::readRequiredStringField($data, 'status'),
            self::readRequiredIntField($data, 'current_node_index'),
            $nodes,
            self::readRequiredIntField($data, 'next_run_at'),
            self::readRequiredIntField($data, 'attempts'),
            $context,
            $logs,
            self::readRequiredIntField($data, 'created_at'),
            self::readRequiredIntField($data, 'updated_at'),
        );
    }

    public static function normalizeBizDate(string $bizDate): string
    {
        $normalized = trim($bizDate);
        if (!self::isValidBizDate($normalized)) {
            throw new RuntimeException(sprintf('ActivityFlow biz_date 格式非法: %s', $normalized));
        }

        return $normalized;
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
        if (!self::isValidBizDate($bizDate)) {
            throw new RuntimeException('ActivityFlow biz_date 必须为 Y-m-d 格式');
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
        if (!array_is_list($nodes)) {
            throw new RuntimeException('ActivityFlow nodes 必须为顺序 list');
        }
        if ($nodes === []) {
            throw new RuntimeException('ActivityFlow nodes 至少包含一个节点');
        }
        foreach ($nodes as $index => $node) {
            if (!$node instanceof ActivityNode) {
                throw new RuntimeException(sprintf(
                    'ActivityFlow nodes[%d] 必须为 ActivityNode',
                    (int)$index,
                ));
            }
        }
        if ($currentNodeIndex >= count($nodes)) {
            throw new RuntimeException('ActivityFlow current_node_index 越界');
        }
        if (!array_is_list($logs)) {
            throw new RuntimeException('ActivityFlow logs 必须为顺序 list');
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

    /**
     * @param array<string, mixed> $data
     */
    private static function readRequiredStringField(array $data, string $field): string
    {
        if (!array_key_exists($field, $data)) {
            throw new RuntimeException(sprintf('ActivityFlow 缺少必填字段: %s', $field));
        }

        $value = $data[$field];
        if (!is_string($value)) {
            throw new RuntimeException(sprintf(
                'ActivityFlow %s 必须为 string，实际类型: %s',
                $field,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    private static function readRequiredArrayField(array $data, string $field): array
    {
        if (!array_key_exists($field, $data)) {
            throw new RuntimeException(sprintf('ActivityFlow 缺少必填字段: %s', $field));
        }

        $value = $data[$field];
        if (!is_array($value)) {
            throw new RuntimeException(sprintf(
                'ActivityFlow %s 必须为 array，实际类型: %s',
                $field,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     */
    private static function assertSequentialList(array $values, string $field): void
    {
        if (!array_is_list($values)) {
            throw new RuntimeException(sprintf('ActivityFlow %s 必须为顺序 list', $field));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readRequiredIntField(array $data, string $field): int
    {
        if (!array_key_exists($field, $data)) {
            throw new RuntimeException(sprintf('ActivityFlow 缺少必填字段: %s', $field));
        }

        $value = $data[$field];
        if (!is_int($value)) {
            throw new RuntimeException(sprintf(
                'ActivityFlow %s 必须为 int，实际类型: %s',
                $field,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    private static function isValidBizDate(string $bizDate): bool
    {
        if ($bizDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bizDate)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $bizDate);
        if (!$date instanceof DateTimeImmutable) {
            return false;
        }

        return $date->format('Y-m-d') === $bizDate;
    }
}
