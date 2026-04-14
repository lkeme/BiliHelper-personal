<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestTrafficInterceptor implements HttpClientInterceptor
{
    /**
     * 初始化 HttpRequestTrafficInterceptor
     * @param HttpRequestTrafficMonitor $monitor
     */
    public function __construct(
        private readonly HttpRequestTrafficMonitor $monitor,
    ) {
    }

    /**
     * 处理beforeSend
     * @param HttpRequestContext $context
     * @return HttpRequestContext
     */
    public function beforeSend(HttpRequestContext $context): HttpRequestContext
    {
        return $context;
    }

    /**
     * 处理after响应
     * @param HttpRequestContext $context
     * @param HttpResponse $response
     * @return void
     */
    public function afterResponse(HttpRequestContext $context, HttpResponse $response): void
    {
        $this->monitor->record((string)($context->attributes['host'] ?? ''), true);
    }

    /**
     * 处理after失败
     * @param HttpRequestContext $context
     * @param \Throwable $exception
     * @return void
     */
    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void
    {
        $this->monitor->record((string)($context->attributes['host'] ?? ''), false);
    }
}
