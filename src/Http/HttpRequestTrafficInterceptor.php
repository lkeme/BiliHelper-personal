<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestTrafficInterceptor implements HttpClientInterceptor
{
    public function __construct(
        private readonly HttpRequestTrafficMonitor $monitor,
    ) {
    }

    public function beforeSend(HttpRequestContext $context): HttpRequestContext
    {
        return $context;
    }

    public function afterResponse(HttpRequestContext $context, HttpResponse $response): void
    {
        $this->monitor->record((string)($context->attributes['host'] ?? ''), true);
    }

    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void
    {
        $this->monitor->record((string)($context->attributes['host'] ?? ''), false);
    }
}
