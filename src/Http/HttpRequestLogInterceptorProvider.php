<?php declare(strict_types=1);

namespace Bhp\Http;

use Bhp\Log\Log;

final class HttpRequestLogInterceptorProvider implements HttpClientInterceptorProvider
{
    /**
     * 初始化 HttpRequestLogInterceptorProvider
     * @param Log $log
     */
    public function __construct(
        private readonly Log $log,
    ) {
    }

    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'request_log';
    }

    /**
     * 处理priority
     * @return int
     */
    public function priority(): int
    {
        return 100;
    }

    /**
     * 处理provide
     * @param HttpRequestContext $context
     * @return array
     */
    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestLogInterceptor($this->log),
        ];
    }
}
