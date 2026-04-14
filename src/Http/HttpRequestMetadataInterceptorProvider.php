<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestMetadataInterceptorProvider implements HttpClientInterceptorProvider
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'request_metadata';
    }

    /**
     * 处理priority
     * @return int
     */
    public function priority(): int
    {
        return 50;
    }

    /**
     * 处理provide
     * @param HttpRequestContext $context
     * @return array
     */
    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestMetadataInterceptor(),
        ];
    }
}
