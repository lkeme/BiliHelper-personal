<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use RuntimeException;

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
        $payload = $data['payload'] ?? [];
        if (!is_array($payload)) {
            throw new RuntimeException('ActivityNodeResult payload 必须为数组');
        }

        return new self(
            self::readOk($data),
            trim((string)($data['message'] ?? '')),
            $payload,
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

    /**
     * @param array<string, mixed> $data
     */
    private static function readOk(array $data): bool
    {
        if (!array_key_exists('ok', $data)) {
            return false;
        }

        $value = $data['ok'];
        if (!is_bool($value)) {
            throw new RuntimeException(sprintf(
                'ActivityNodeResult ok 必须为 bool，实际类型: %s',
                get_debug_type($value),
            ));
        }

        return $value;
    }
}
