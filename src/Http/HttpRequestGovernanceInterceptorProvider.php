<?php declare(strict_types=1);

namespace Bhp\Http;

use Bhp\Runtime\AppContext;

final class HttpRequestGovernanceInterceptorProvider implements HttpClientInterceptorProvider
{
    public function __construct(
        private readonly AppContext $context,
        private readonly HttpRequestTrafficMonitor $monitor,
    ) {
    }

    public function name(): string
    {
        return 'request_governance';
    }

    public function priority(): int
    {
        return 85;
    }

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

    private function enabled(): bool
    {
        return (bool)$this->context->config('request_governance.enable', false, 'bool');
    }

    private function mode(): string
    {
        return (string)$this->context->config('request_governance.mode', 'observe', 'string');
    }

    private function windowSeconds(): int
    {
        return max(1, (int)$this->context->config('request_governance.window_seconds', 60, 'int'));
    }

    private function maxRequestsPerHost(): int
    {
        return max(1, (int)$this->context->config('request_governance.max_requests_per_host', 60, 'int'));
    }

    private function cooldownSeconds(): int
    {
        return max(1, (int)$this->context->config('request_governance.cooldown_seconds', 30, 'int'));
    }
}
