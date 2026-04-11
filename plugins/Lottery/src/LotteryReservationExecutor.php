<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\Api\Support\ApiJson;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Request\Request;
use Bhp\Util\Exceptions\NoLoginException;
use Throwable;

class LotteryReservationExecutor
{
    private AuthFailureClassifier $authFailureClassifier;

    public function __construct(
        private readonly LotteryReservationService $reservationService,
        private readonly Request $request,
    ) {
        $this->authFailureClassifier = new AuthFailureClassifier();
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
        $this->assertNotAuthFailure($response, sprintf(
            '抽奖: 预约 ReserveId=%s 时账号未登录',
            (string)($lottery['rid'] ?? ''),
        ));

        return $this->reservationService->evaluateReserveResponse($response, $lottery);
    }

    /**
     * @param array<string, int|string> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    protected function postJson(string $url, array $payload, array $headers): array
    {
        try {
            $raw = $this->request->postText('pc', $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'lottery.reserve 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'lottery.reserve');
    }

    /**
     * @param array<string, mixed> $response
     * @throws NoLoginException
     */
    protected function assertNotAuthFailure(array $response, string $fallbackMessage): void
    {
        $this->authFailureClassifier->assertNotAuthFailure($response, $fallbackMessage);
    }
}
