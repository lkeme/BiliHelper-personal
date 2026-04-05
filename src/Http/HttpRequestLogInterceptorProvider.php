<?php declare(strict_types=1);

namespace Bhp\Http;

use Bhp\Log\Log;

final class HttpRequestLogInterceptorProvider implements HttpClientInterceptorProvider
{
    public function __construct(
        private readonly Log $log,
    ) {
    }

    public function name(): string
    {
        return 'request_log';
    }

    public function priority(): int
    {
        return 100;
    }

    public function provide(HttpRequestContext $context): array
    {
        return [
            new HttpRequestLogInterceptor($this->log),
        ];
    }
}
