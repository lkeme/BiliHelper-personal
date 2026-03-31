<?php declare(strict_types=1);

namespace Bhp\Http;

interface HttpClientInterceptor
{
    public function beforeSend(HttpRequestContext $context): HttpRequestContext;

    public function afterResponse(HttpRequestContext $context, HttpResponse $response): void;

    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void;
}
