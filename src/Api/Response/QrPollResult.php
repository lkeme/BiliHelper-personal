<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class QrPollResult
{
    /**
     * @param array<string, mixed> $response
     */
    private function __construct(
        public readonly bool $confirmed,
        public readonly string $message,
        public readonly array $response = [],
    ) {
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function confirmed(string $message, array $response): self
    {
        return new self(true, $message, $response);
    }

    public static function pending(string $message): self
    {
        return new self(false, $message);
    }
}
