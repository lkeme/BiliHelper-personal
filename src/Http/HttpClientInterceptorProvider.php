<?php declare(strict_types=1);

namespace Bhp\Http;

interface HttpClientInterceptorProvider
{
    public function name(): string;

    public function priority(): int;

    /**
     * @return HttpClientInterceptor[]
     */
    public function provide(HttpRequestContext $context): array;
}
