<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\Request\Request;

class LotteryReservationExecutor
{
    public function __construct(
        private readonly LotteryReservationService $reservationService,
    ) {
    }

    /**
     * @param array<string, mixed> $lottery
     * @return array{success: bool, message: string}
     */
    public function reserve(
        array $lottery,
        string $csrf,
        string $referer,
    ): array {
        $request = $this->reservationService->buildReserveRequest($lottery, $csrf, $referer);

        $response = $this->postJson($request['url'], $request['payload'], $request['headers']);

        return $this->reservationService->evaluateReserveResponse($response, $lottery);
    }

    /**
     * @param array<string, int|string> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function postJson(string $url, array $payload, array $headers): array
    {
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }
}
