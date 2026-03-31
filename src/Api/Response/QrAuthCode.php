<?php declare(strict_types=1);

namespace Bhp\Api\Response;

final class QrAuthCode
{
    public function __construct(
        public readonly string $authCode,
        public readonly string $url,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['auth_code'] ?? ''),
            (string)($data['url'] ?? ''),
        );
    }
}
