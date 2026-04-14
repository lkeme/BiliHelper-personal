<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow;

use RuntimeException;

final class ActivityNode
{
    private readonly string $type;
    private readonly string $status;

    /**
     * @var array<string, mixed>
     */
    private readonly array $payload;
    /**
     * @var array<string, mixed>
     */
    private readonly array $context;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $type,
        array $payload = [],
        string $status = ActivityNodeStatus::PENDING,
        array $context = [],
        private readonly ?ActivityNodeResult $result = null,
        private readonly int $attempts = 0,
    ) {
        $this->type = trim($type);
        $this->payload = $payload;
        $this->status = trim($status);
        $this->context = $context;

        self::assertValid($this->type, $this->status, $this->attempts);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $result = null;
        if (array_key_exists('result', $data)) {
            if ($data['result'] !== null && !is_array($data['result'])) {
                throw new RuntimeException('ActivityNode result 必须为数组或 null');
            }
            if (is_array($data['result'])) {
                $result = ActivityNodeResult::fromArray($data['result']);
            }
        }

        $context = self::readRequiredArrayField($data, 'context');
        $payload = self::readRequiredArrayField($data, 'payload');

        return new self(
            self::readRequiredStringField($data, 'type'),
            $payload,
            self::readRequiredStringField($data, 'status'),
            $context,
            $result,
            self::readRequiredAttempts($data),
        );
    }

    /**
     * 获取类型标识
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * 处理状态
     * @return string
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * 处理结果
     * @return ?ActivityNodeResult
     */
    public function result(): ?ActivityNodeResult
    {
        return $this->result;
    }

    /**
     * 处理attempts
     * @return int
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'status' => $this->status,
            'payload' => $this->payload,
            'context' => $this->context,
            'attempts' => $this->attempts,
            'result' => $this->result?->toArray(),
        ];
    }

    /**
     * @return string[]
     */
    private static function allowedStatuses(): array
    {
        return [
            ActivityNodeStatus::PENDING,
            ActivityNodeStatus::RUNNING,
            ActivityNodeStatus::WAITING,
            ActivityNodeStatus::SUCCEEDED,
            ActivityNodeStatus::SKIPPED,
            ActivityNodeStatus::FAILED,
        ];
    }

    /**
     * 断言Valid
     * @param string $type
     * @param string $status
     * @param int $attempts
     * @return void
     */
    private static function assertValid(string $type, string $status, int $attempts): void
    {
        if (trim($type) === '') {
            throw new RuntimeException('ActivityNode type 不能为空');
        }
        if (!in_array($status, self::allowedStatuses(), true)) {
            throw new RuntimeException('ActivityNode 状态非法: ' . $status);
        }
        if ($attempts < 0) {
            throw new RuntimeException('ActivityNode attempts 不能为负数');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readRequiredStringField(array $data, string $field): string
    {
        if (!array_key_exists($field, $data)) {
            throw new RuntimeException(sprintf('ActivityNode 缺少必填字段: %s', $field));
        }

        $value = $data[$field];
        if (!is_string($value)) {
            throw new RuntimeException(sprintf(
                'ActivityNode %s 必须为 string，实际类型: %s',
                $field,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readRequiredAttempts(array $data): int
    {
        if (!array_key_exists('attempts', $data)) {
            throw new RuntimeException('ActivityNode 缺少必填字段: attempts');
        }

        $value = $data['attempts'];
        if (!is_int($value)) {
            throw new RuntimeException(sprintf(
                'ActivityNode attempts 必须为 int，实际类型: %s',
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
            throw new RuntimeException(sprintf('ActivityNode 缺少必填字段: %s', $field));
        }

        $value = $data[$field];
        if (!is_array($value)) {
            throw new RuntimeException(sprintf(
                'ActivityNode %s 必须为 array，实际类型: %s',
                $field,
                get_debug_type($value),
            ));
        }

        return $value;
    }
}

