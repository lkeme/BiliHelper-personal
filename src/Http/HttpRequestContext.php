<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestContext
{
    /**
     * @param HttpClientInterceptor[]|null $interceptors
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $method,
        public string $url,
        public RequestOptions $options,
        public string $requestId,
        public ?array $interceptors = null,
        public array $attributes = [],
    ) {
    }
}
