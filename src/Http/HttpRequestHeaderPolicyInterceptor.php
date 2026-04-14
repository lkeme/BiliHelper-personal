<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestHeaderPolicyInterceptor implements HttpClientInterceptor
{
    /**
     * 处理beforeSend
     * @param HttpRequestContext $context
     * @return HttpRequestContext
     */
    public function beforeSend(HttpRequestContext $context): HttpRequestContext
    {
        if (!$this->hasHeader($context->options->headers, 'X-Request-Id')) {
            $context->options->headers['X-Request-Id'] = $context->requestId;
        }

        if (($context->options->sink !== null && trim($context->options->sink) !== '')
            && !$this->hasHeader($context->options->headers, 'Accept-Encoding')) {
            $context->options->headers['Accept-Encoding'] = 'identity';
        }

        $context->attributes['request_id_header'] = $this->firstHeaderValue($context->options->headers, 'X-Request-Id');
        $context->attributes['accept_encoding'] = $this->firstHeaderValue($context->options->headers, 'Accept-Encoding');

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
    }

    /**
     * 处理after失败
     * @param HttpRequestContext $context
     * @param \Throwable $exception
     * @return void
     */
    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void
    {
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function firstHeaderValue(array $headers, string $name): string
    {
        foreach ($headers as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                return is_scalar($value) ? (string)$value : '';
            }
        }

        return '';
    }
}
