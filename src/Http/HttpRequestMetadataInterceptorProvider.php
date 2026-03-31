<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestMetadataInterceptorProvider implements HttpClientInterceptorProvider
{
    public function name(): string
    {
        return 'request_metadata';
    }

    public function priority(): int
    {
        return 50;
    }

    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestMetadataInterceptor(),
        ];
    }
}
