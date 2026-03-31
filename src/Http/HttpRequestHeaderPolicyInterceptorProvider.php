<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestHeaderPolicyInterceptorProvider implements HttpClientInterceptorProvider
{
    public function name(): string
    {
        return 'request_header_policy';
    }

    public function priority(): int
    {
        return 60;
    }

    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestHeaderPolicyInterceptor(),
        ];
    }
}
