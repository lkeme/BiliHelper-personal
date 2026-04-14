<?php declare(strict_types=1);

namespace Bhp\Http;

interface HttpClientInterceptor
{
    /**
     * 处理beforeSend
     * @param HttpRequestContext $context
     * @return HttpRequestContext
     */
    public function beforeSend(HttpRequestContext $context): HttpRequestContext;

    /**
     * 处理after响应
     * @param HttpRequestContext $context
     * @param HttpResponse $response
     * @return void
     */
    public function afterResponse(HttpRequestContext $context, HttpResponse $response): void;

    /**
     * 处理after失败
     * @param HttpRequestContext $context
     * @param \Throwable $exception
     * @return void
     */
    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void;
}
