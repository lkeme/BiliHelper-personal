<?php declare(strict_types=1);

namespace Bhp\Http;

interface HttpClientInterceptorProvider
{
    /**
     * 处理名称
     * @return string
     */
    public function name(): string;

    /**
     * 处理priority
     * @return int
     */
    public function priority(): int;

    /**
     * @return HttpClientInterceptor[]
     */
    public function provide(HttpRequestContext $context): array;
}
