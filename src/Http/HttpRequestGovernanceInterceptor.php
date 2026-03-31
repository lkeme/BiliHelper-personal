<?php declare(strict_types=1);

namespace Bhp\Http;

use Bhp\Util\Exceptions\RequestException;

final class HttpRequestGovernanceInterceptor implements HttpClientInterceptor
{
    public function __construct(
        private readonly bool $enabled = false,
        private readonly string $mode = 'observe',
        private readonly int $windowSeconds = 60,
        private readonly int $maxRequestsPerHost = 60,
        private readonly int $cooldownSeconds = 30,
        private readonly ?HttpRequestTrafficMonitor $monitor = null,
    ) {
    }

    public function beforeSend(HttpRequestContext $context): HttpRequestContext
    {
        $host = (string)($context->attributes['host'] ?? '');
        $context->attributes['governance_enabled'] = $this->enabled;
        $context->attributes['governance_mode'] = $this->mode;
        $context->attributes['governance_window_seconds'] = $this->windowSeconds;
        $context->attributes['governance_max_requests_per_host'] = $this->maxRequestsPerHost;
        $context->attributes['governance_cooldown_seconds'] = $this->cooldownSeconds;

        if (!$this->enabled || $host === '' || $this->maxRequestsPerHost < 1) {
            $context->attributes['governance_state'] = 'disabled';
            $context->attributes['governance_remaining_seconds'] = 0;
            $context->attributes['governance_request_count'] = 0;

            return $context;
        }

        $requestCount = $this->monitor()->hostRequestCount($host, $this->windowSeconds);
        $remaining = $this->monitor()->cooldownRemaining($host, $this->windowSeconds, $this->maxRequestsPerHost, $this->cooldownSeconds);
        $isHot = $requestCount >= $this->maxRequestsPerHost;

        $context->attributes['governance_request_count'] = $requestCount;
        $context->attributes['governance_remaining_seconds'] = round($remaining, 2);
        $context->attributes['governance_state'] = $isHot ? ($remaining > 0 ? 'cooldown' : 'hot') : 'normal';

        if ($this->mode === 'enforce' && $remaining > 0) {
            throw new RequestException(
                "请求治理限制: host {$host} 进入冷却窗口",
                strtoupper($context->method),
                $context->url,
                0,
                null,
                RequestException::CATEGORY_GOVERNED,
                $context->requestId,
            );
        }

        return $context;
    }

    public function afterResponse(HttpRequestContext $context, HttpResponse $response): void
    {
    }

    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void
    {
    }

    private function monitor(): HttpRequestTrafficMonitor
    {
        return $this->monitor ?? HttpRequestTrafficMonitor::getInstance();
    }
}
