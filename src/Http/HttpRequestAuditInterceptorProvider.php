<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestAuditInterceptorProvider implements HttpClientInterceptorProvider
{
    public function name(): string
    {
        return 'request_audit';
    }

    public function priority(): int
    {
        return 70;
    }

    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestAuditInterceptor(),
        ];
    }
}
