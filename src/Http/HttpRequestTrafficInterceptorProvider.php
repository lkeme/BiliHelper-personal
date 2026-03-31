<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestTrafficInterceptorProvider implements HttpClientInterceptorProvider
{
    public function name(): string
    {
        return 'request_traffic';
    }

    public function priority(): int
    {
        return 80;
    }

    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestTrafficInterceptor(),
        ];
    }
}
