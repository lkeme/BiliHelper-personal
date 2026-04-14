<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestAuditInterceptorProvider implements HttpClientInterceptorProvider
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'request_audit';
    }

    /**
     * 处理priority
     * @return int
     */
    public function priority(): int
    {
        return 70;
    }

    /**
     * 处理provide
     * @param HttpRequestContext $context
     * @return array
     */
    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestAuditInterceptor(),
        ];
    }
}
