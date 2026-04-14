<?php declare(strict_types=1);

namespace Bhp\Http;

final class HttpRequestTrafficInterceptorProvider implements HttpClientInterceptorProvider
{
    /**
     * 初始化 HttpRequestTrafficInterceptorProvider
     * @param HttpRequestTrafficMonitor $monitor
     */
    public function __construct(
        private readonly HttpRequestTrafficMonitor $monitor,
    ) {
    }

    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'request_traffic';
    }

    /**
     * 处理priority
     * @return int
     */
    public function priority(): int
    {
        return 80;
    }

    /**
     * 处理provide
     * @param HttpRequestContext $context
     * @return array
     */
    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestTrafficInterceptor($this->monitor),
        ];
    }
}
