<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use RuntimeException;

final class ActivityNode
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $type,
        private readonly array $payload = [],
        private readonly string $status = ActivityNodeStatus::PENDING,
        private readonly array $context = [],
        private readonly ?ActivityNodeResult $result = null,
        private readonly int $attempts = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = trim((string)($data['status'] ?? ActivityNodeStatus::PENDING));
        if (!in_array($status, self::allowedStatuses(), true)) {
            throw new RuntimeException('ActivityNode 状态非法: ' . $status);
        }
        $attempts = (int)($data['attempts'] ?? 0);
        if ($attempts < 0) {
            throw new RuntimeException('ActivityNode attempts 不能为负数');
        }

        $result = null;
        if (is_array($data['result'] ?? null)) {
            $result = ActivityNodeResult::fromArray($data['result']);
        }

        return new self(
            trim((string)($data['type'] ?? '')),
            is_array($data['payload'] ?? null) ? $data['payload'] : [],
            $status,
            is_array($data['context'] ?? null) ? $data['context'] : [],
            $result,
            $attempts,
        );
    }

    public function type(): string
    {
        return $this->type;
    }

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

    public function result(): ?ActivityNodeResult
    {
        return $this->result;
    }

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
}
