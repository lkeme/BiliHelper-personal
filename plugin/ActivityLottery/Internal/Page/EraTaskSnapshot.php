<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Page;

final class EraTaskSnapshot
{
    public function __construct(
        private readonly string $taskId,
        private readonly string $taskName,
        private readonly string $capability,
        private readonly int $taskStatus,
        private readonly int $taskAwardType,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            trim((string)($data['task_id'] ?? $data['taskId'] ?? '')),
            trim((string)($data['task_name'] ?? $data['taskName'] ?? '')),
            trim((string)($data['capability'] ?? '')),
            (int)($data['task_status'] ?? $data['taskStatus'] ?? 0),
            (int)($data['task_award_type'] ?? $data['taskAwardType'] ?? 0),
        );
    }

    public function taskId(): string
    {
        return $this->taskId;
    }

    public function taskName(): string
    {
        return $this->taskName;
    }

    public function capability(): string
    {
        return $this->capability;
    }

    public function taskStatus(): int
    {
        return $this->taskStatus;
    }

    public function taskAwardType(): int
    {
        return $this->taskAwardType;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task_id' => $this->taskId,
            'task_name' => $this->taskName,
            'capability' => $this->capability,
            'task_status' => $this->taskStatus,
            'task_award_type' => $this->taskAwardType,
        ];
    }
}

