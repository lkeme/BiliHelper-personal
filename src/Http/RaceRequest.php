<?php declare(strict_types=1);

namespace Bhp\Http;

use Amp\DeferredCancellation;
use Amp\Future;
use function Amp\async;
use function Amp\Future\awaitFirst;

class RaceRequest
{
    /**
     * @param array<int, array{method?: string, url: string, options?: RequestOptions}> $requests
     * @param callable(HttpResponse): bool|null $successPredicate
     */
    public function run(array $requests, int $concurrency = 5, ?callable $successPredicate = null): ?HttpResponse
    {
        if ($requests === []) {
            return null;
        }

        $deferredCancellation = new DeferredCancellation();
        $futures = [];

        foreach (array_slice($requests, 0, max(1, $concurrency), true) as $key => $request) {
            $options = $request['options'] ?? new RequestOptions();
            $options->cancellation = $deferredCancellation->getCancellation();

            $futures[$key] = async(function () use ($request, $options) {
                return HttpClient::getInstance()->send(
                    $request['method'] ?? 'GET',
                    $request['url'],
                    $options,
                );
            });
        }

        try {
            $response = awaitFirst($futures, $deferredCancellation->getCancellation());
            if ($successPredicate === null || $successPredicate($response)) {
                $deferredCancellation->cancel();
                return $response;
            }
        } finally {
            $deferredCancellation->cancel();
        }

        return null;
    }
}
