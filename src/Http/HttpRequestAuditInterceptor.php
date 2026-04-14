<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestAuditInterceptor implements HttpClientInterceptor
{
    /**
     * 处理beforeSend
     * @param HttpRequestContext $context
     * @return HttpRequestContext
     */
    public function beforeSend(HttpRequestContext $context): HttpRequestContext
    {
        $context->attributes['body_mode'] = $this->resolveBodyMode($context->options);
        $context->attributes['query_count'] = count($context->options->query);
        $context->attributes['header_count'] = count($context->options->headers);
        $context->attributes['body_field_count'] = $this->resolveBodyFieldCount($context->options);
        $context->attributes['raw_body_bytes'] = $this->resolveRawBodyBytes($context->options);

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
     * 解析Body模式
     * @param RequestOptions $options
     * @return string
     */
    private function resolveBodyMode(RequestOptions $options): string
    {
        if ($options->json !== null) {
            return 'json';
        }

        if ($options->formParams !== null) {
            return 'form';
        }

        if ($options->body !== null && $options->body !== '') {
            return 'raw';
        }

        if ($options->query !== []) {
            return 'query';
        }

        return 'none';
    }

    /**
     * 解析Body字段数量
     * @param RequestOptions $options
     * @return int
     */
    private function resolveBodyFieldCount(RequestOptions $options): int
    {
        if ($options->json !== null) {
            return count($options->json);
        }

        if ($options->formParams !== null) {
            return count($options->formParams);
        }

        return 0;
    }

    /**
     * 解析RawBodyBytes
     * @param RequestOptions $options
     * @return int
     */
    private function resolveRawBodyBytes(RequestOptions $options): int
    {
        if ($options->body === null || $options->body === '') {
            return 0;
        }

        return strlen($options->body);
    }
}
