<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow;

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
        return new self(
            self::readRequiredOk($data),
            self::readRequiredMessage($data),
            self::readRequiredPayload($data),
            self::readCreatedAt($data),
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
    private static function readRequiredOk(array $data): bool
    {
        if (!array_key_exists('ok', $data)) {
            throw new RuntimeException('ActivityNodeResult 缺少必填字段: ok');
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

    /**
     * @param array<string, mixed> $data
     */
    private static function readRequiredMessage(array $data): string
    {
        if (!array_key_exists('message', $data)) {
            throw new RuntimeException('ActivityNodeResult 缺少必填字段: message');
        }

        $value = $data['message'];
        if (!is_string($value)) {
            throw new RuntimeException(sprintf(
                'ActivityNodeResult message 必须为 string，实际类型: %s',
                get_debug_type($value),
            ));
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readCreatedAt(array $data): int
    {
        if (!array_key_exists('created_at', $data)) {
            return 0;
        }

        $value = $data['created_at'];
        if (!is_int($value)) {
            throw new RuntimeException(sprintf(
                'ActivityNodeResult created_at 必须为 int，实际类型: %s',
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function readRequiredPayload(array $data): array
    {
        if (!array_key_exists('payload', $data)) {
            throw new RuntimeException('ActivityNodeResult 缺少必填字段: payload');
        }

        $value = $data['payload'];
        if (!is_array($value)) {
            throw new RuntimeException(sprintf(
                'ActivityNodeResult payload 必须为 array，实际类型: %s',
                get_debug_type($value),
            ));
        }

        return $value;
    }
}

