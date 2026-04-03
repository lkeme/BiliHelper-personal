<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

final class ActivityNodeResult
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly bool $ok,
        private readonly string $message = '',
        private readonly array $payload = [],
        private readonly int $createdAt = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (bool)($data['ok'] ?? false),
            trim((string)($data['message'] ?? '')),
            is_array($data['payload'] ?? null) ? $data['payload'] : [],
            (int)($data['created_at'] ?? 0),
        );
    }

    public function ok(): bool
    {
        return $this->ok;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function createdAt(): int
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'message' => $this->message,
            'payload' => $this->payload,
            'created_at' => $this->createdAt,
        ];
    }
}
