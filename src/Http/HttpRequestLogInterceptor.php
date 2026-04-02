<?php declare(strict_types=1);

namespace Bhp\Http;

use Bhp\Log\Log;

final class HttpRequestLogInterceptor implements HttpClientInterceptor
{
    public function beforeSend(HttpRequestContext $context): HttpRequestContext
    {
        return $context;
    }

    public function afterResponse(HttpRequestContext $context, HttpResponse $response): void
    {
        if (($context->attributes['quiet'] ?? false) === true) {
            return;
        }

        $transport = $this->transportSummary($context);
        Log::debug(
            sprintf('[AMP#%s] %s %s %d %.2fms%s', $context->requestId, strtoupper($context->method), $context->url, $response->getStatusCode(), $response->getDurationMs(), $transport),
            [
                'request_id' => $context->requestId,
                'method' => strtoupper($context->method),
                'url' => $context->url,
                'status' => $response->getStatusCode(),
                'duration_ms' => round($response->getDurationMs(), 2),
                'host' => (string)($context->attributes['host'] ?? ''),
                'path' => (string)($context->attributes['path'] ?? ''),
                'proxy_enabled' => (bool)($context->attributes['proxy_enabled'] ?? false),
                'sink_enabled' => (bool)($context->attributes['sink_enabled'] ?? false),
                'follow_redirects' => (bool)($context->attributes['follow_redirects'] ?? true),
                'request_id_header' => (string)($context->attributes['request_id_header'] ?? ''),
                'accept_encoding' => (string)($context->attributes['accept_encoding'] ?? ''),
                'body_mode' => (string)($context->attributes['body_mode'] ?? 'none'),
                'query_count' => (int)($context->attributes['query_count'] ?? 0),
                'header_count' => (int)($context->attributes['header_count'] ?? 0),
                'body_field_count' => (int)($context->attributes['body_field_count'] ?? 0),
                'raw_body_bytes' => (int)($context->attributes['raw_body_bytes'] ?? 0),
                'governance_enabled' => (bool)($context->attributes['governance_enabled'] ?? false),
                'governance_mode' => (string)($context->attributes['governance_mode'] ?? ''),
                'governance_state' => (string)($context->attributes['governance_state'] ?? ''),
                'governance_request_count' => (int)($context->attributes['governance_request_count'] ?? 0),
                'governance_remaining_seconds' => $context->attributes['governance_remaining_seconds'] ?? 0,
                'timeout_ms' => $context->attributes['timeout_ms'] ?? null,
                'task' => 'http.send',
            ]
        );
    }

    public function afterFailure(HttpRequestContext $context, \Throwable $exception): void
    {
        if (($context->attributes['quiet'] ?? false) === true) {
            return;
        }

        $transport = $this->transportSummary($context);
        Log::warning(
            sprintf('[AMP#%s] %s %s failed: %s%s', $context->requestId, strtoupper($context->method), $context->url, $exception->getMessage(), $transport),
            [
                'request_id' => $context->requestId,
                'method' => strtoupper($context->method),
                'url' => $context->url,
                'error_code' => $exception->getCode(),
                'host' => (string)($context->attributes['host'] ?? ''),
                'path' => (string)($context->attributes['path'] ?? ''),
                'proxy_enabled' => (bool)($context->attributes['proxy_enabled'] ?? false),
                'sink_enabled' => (bool)($context->attributes['sink_enabled'] ?? false),
                'follow_redirects' => (bool)($context->attributes['follow_redirects'] ?? true),
                'request_id_header' => (string)($context->attributes['request_id_header'] ?? ''),
                'accept_encoding' => (string)($context->attributes['accept_encoding'] ?? ''),
                'body_mode' => (string)($context->attributes['body_mode'] ?? 'none'),
                'query_count' => (int)($context->attributes['query_count'] ?? 0),
                'header_count' => (int)($context->attributes['header_count'] ?? 0),
                'body_field_count' => (int)($context->attributes['body_field_count'] ?? 0),
                'raw_body_bytes' => (int)($context->attributes['raw_body_bytes'] ?? 0),
                'governance_enabled' => (bool)($context->attributes['governance_enabled'] ?? false),
                'governance_mode' => (string)($context->attributes['governance_mode'] ?? ''),
                'governance_state' => (string)($context->attributes['governance_state'] ?? ''),
                'governance_request_count' => (int)($context->attributes['governance_request_count'] ?? 0),
                'governance_remaining_seconds' => $context->attributes['governance_remaining_seconds'] ?? 0,
                'timeout_ms' => $context->attributes['timeout_ms'] ?? null,
                'task' => 'http.send',
            ]
        );
    }

    private function transportSummary(HttpRequestContext $context): string
    {
        $segments = [];
        $segments[] = 'proxy=' . ((bool)($context->attributes['proxy_enabled'] ?? false) ? 'yes' : 'no');
        $segments[] = 'sink=' . ((bool)($context->attributes['sink_enabled'] ?? false) ? 'yes' : 'no');
        $segments[] = 'redirect=' . ((bool)($context->attributes['follow_redirects'] ?? true) ? 'yes' : 'no');
        if (($context->attributes['request_id_header'] ?? '') !== '') {
            $segments[] = 'request_id_header=yes';
        }
        if (($context->attributes['accept_encoding'] ?? '') !== '') {
            $segments[] = 'accept_encoding=' . (string)$context->attributes['accept_encoding'];
        }
        $segments[] = 'body_mode=' . (string)($context->attributes['body_mode'] ?? 'none');
        $segments[] = 'query_count=' . (string)($context->attributes['query_count'] ?? 0);
        $segments[] = 'header_count=' . (string)($context->attributes['header_count'] ?? 0);
        if (($context->attributes['body_field_count'] ?? 0) > 0) {
            $segments[] = 'body_fields=' . (string)$context->attributes['body_field_count'];
        }
        if (($context->attributes['raw_body_bytes'] ?? 0) > 0) {
            $segments[] = 'raw_body_bytes=' . (string)$context->attributes['raw_body_bytes'];
        }
        if (($context->attributes['governance_enabled'] ?? false) === true) {
            $segments[] = 'governance=' . (string)($context->attributes['governance_mode'] ?? 'observe');
            $segments[] = 'governance_state=' . (string)($context->attributes['governance_state'] ?? 'normal');
            if (($context->attributes['governance_request_count'] ?? 0) > 0) {
                $segments[] = 'governance_requests=' . (string)$context->attributes['governance_request_count'];
            }
            if (($context->attributes['governance_remaining_seconds'] ?? 0) > 0) {
                $segments[] = 'governance_remaining=' . (string)$context->attributes['governance_remaining_seconds'];
            }
        }

        if (isset($context->attributes['timeout_ms']) && is_scalar($context->attributes['timeout_ms'])) {
            $segments[] = 'timeout_ms=' . (string)$context->attributes['timeout_ms'];
        }

        return ' [' . implode(' ', $segments) . ']';
    }
}
