<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpResponse
{
    /**
     * 初始化 HttpResponse
     * @param int $statusCode
     * @param array $headers
     * @param string $body
     * @param float $durationMs
     * @param string $requestId
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $body,
        private readonly float $durationMs,
        private readonly string $requestId,
    ) {
    }

    /**
     * 获取状态状态码
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 获取Headers
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 获取Body
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * 获取DurationMs
     * @return float
     */
    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    /**
     * 获取请求Id
     * @return string
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }
}
