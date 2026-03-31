<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestMetadataInterceptor implements HttpClientInterceptor
{
    public function beforeSend(HttpRequestContext $context): HttpRequestContext
    {
        $parts = parse_url($context->url);

        $context->attributes['scheme'] = isset($parts['scheme']) ? (string)$parts['scheme'] : '';
        $context->attributes['host'] = isset($parts['host']) ? (string)$parts['host'] : '';
        $context->attributes['path'] = isset($parts['path']) ? (string)$parts['path'] : '/';
        $context->attributes['proxy_enabled'] = $context->options->proxy !== null && trim($context->options->proxy) !== '';
        $context->attributes['sink_enabled'] = $context->options->sink !== null && trim($context->options->sink) !== '';
        $context->attributes['follow_redirects'] = $context->options->followRedirects;
        $context->attributes['timeout_ms'] = round($context->options->timeout * 1000, 2);

        return $context;
    }

    public function afterResponse(HttpRequestContext $context, HttpResponse $response): void
    {
    }

    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void
    {
    }
}
