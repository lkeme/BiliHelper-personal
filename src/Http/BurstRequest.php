<?php declare(strict_types=1);

namespace Bhp\Http;

use Amp\DeferredCancellation;

class BurstRequest
{
    /**
     * 初始化 BurstRequest
     * @param HttpClient $httpClient
     */
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {
    }

    /**
     * @param array<int, array{method?: string, url: string, options?: RequestOptions}> $requests
     * @param callable(HttpResponse): bool|null $successPredicate
     */
    public function runAt(
        float $startAt,
        array $requests,
        int $burstSize = 3,
        int $waves = 1,
        float $waveInterval = 0.05,
        ?callable $successPredicate = null,
    ): BurstRequestResult {
        $delayUs = (int)max(0, ($startAt - microtime(true)) * 1_000_000);
        if ($delayUs > 0) {
            usleep($delayUs);
        }

        $allWaves = [];
        $matchedWave = -1;
        $matched = false;

        for ($wave = 0; $wave < $waves; $wave++) {
            $waveResponses = $this->runWave($requests, $burstSize, $successPredicate, $matched);
            $allWaves[$wave] = $waveResponses;

            if ($matched) {
                $matchedWave = $wave;
                break;
            }

            if ($wave < $waves - 1) {
                $intervalUs = (int)max(0, $waveInterval * 1_000_000);
                if ($intervalUs > 0) {
                    usleep($intervalUs);
                }
            }
        }

        return new BurstRequestResult($allWaves, $matched, $matchedWave);
    }

    /**
     * @param array<int, array{method?: string, url: string, options?: RequestOptions}> $requests
     * @param callable(HttpResponse): bool|null $successPredicate
     * @param bool $matched
     * @return array<int|string, HttpResponse>
     */
    private function runWave(array $requests, int $burstSize, ?callable $successPredicate, bool &$matched): array
    {
        $deferredCancellation = new DeferredCancellation();
        $prepared = [];

        foreach ($requests as $key => $request) {
            $options = $request['options'] ?? new RequestOptions();
            $options->cancellation = $deferredCancellation->getCancellation();
            $prepared[$key] = [
                'method' => $request['method'] ?? 'GET',
                'url' => $request['url'],
                'options' => $options,
            ];
        }

        $responses = $this->httpClient->sendConcurrent($prepared, $burstSize);

        if ($successPredicate !== null) {
            foreach ($responses as $response) {
                if ($successPredicate($response)) {
                    $matched = true;
                    $deferredCancellation->cancel();
                    break;
                }
            }
        }

        return $responses;
    }
}
