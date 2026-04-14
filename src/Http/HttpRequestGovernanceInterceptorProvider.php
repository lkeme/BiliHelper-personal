<?php declare(strict_types=1);

namespace Bhp\Http;

use Bhp\Runtime\AppContext;

final class HttpRequestGovernanceInterceptorProvider implements HttpClientInterceptorProvider
{
    /**
     * 初始化 HttpRequestGovernanceInterceptorProvider
     * @param AppContext $context
     * @param HttpRequestTrafficMonitor $monitor
     */
    public function __construct(
        private readonly AppContext $context,
        private readonly HttpRequestTrafficMonitor $monitor,
    ) {
    }

    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return 'request_governance';
    }

    /**
     * 处理priority
     * @return int
     */
    public function priority(): int
    {
        return 85;
    }

    /**
     * 处理provide
     * @param HttpRequestContext $context
     * @return array
     */
    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestGovernanceInterceptor(
                $this->enabled(),
                $this->mode(),
                $this->windowSeconds(),
                $this->maxRequestsPerHost(),
                $this->cooldownSeconds(),
                $this->monitor,
            ),
        ];
    }

    /**
     * 处理enabled
     * @return bool
     */
    private function enabled(): bool
    {
        return (bool)$this->context->config('request_governance.enable', false, 'bool');
    }

    /**
     * 处理模式
     * @return string
     */
    private function mode(): string
    {
        return (string)$this->context->config('request_governance.mode', 'observe', 'string');
    }

    /**
     * 处理窗口Seconds
     * @return int
     */
    private function windowSeconds(): int
    {
        return max(1, (int)$this->context->config('request_governance.window_seconds', 60, 'int'));
    }

    /**
     * 处理maxRequestsPer主机
     * @return int
     */
    private function maxRequestsPerHost(): int
    {
        return max(1, (int)$this->context->config('request_governance.max_requests_per_host', 60, 'int'));
    }

    /**
     * 处理cooldownSeconds
     * @return int
     */
    private function cooldownSeconds(): int
    {
        return max(1, (int)$this->context->config('request_governance.cooldown_seconds', 30, 'int'));
    }
}
