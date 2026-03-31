<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpResponse
{
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $body,
        private readonly float $durationMs,
        private readonly string $requestId,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }
}
