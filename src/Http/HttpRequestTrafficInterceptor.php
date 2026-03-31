<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestTrafficInterceptor implements HttpClientInterceptor
{
    public function beforeSend(HttpRequestContext $context): HttpRequestContext
    {
        return $context;
    }

    public function afterResponse(HttpRequestContext $context, HttpResponse $response): void
    {
        $this->monitor()->record((string)($context->attributes['host'] ?? ''), true);
    }

    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void
    {
        $this->monitor()->record((string)($context->attributes['host'] ?? ''), false);
    }

    protected function monitor(): HttpRequestTrafficMonitor
    {
        return HttpRequestTrafficMonitor::getInstance();
    }
}
