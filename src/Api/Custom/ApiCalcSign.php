<?php declare(strict_types=1);

namespace Bhp\Api\Custom;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiCalcSign
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @param array<string, mixed> $t
     * @param array<int, int> $r
     * @return array<string, mixed>
     */
    public function heartBeat(string $url, array $t, array $r): array
    {
        try {
            $raw = $this->request->postJsonBodyText('other', $url, [
                't' => $t,
                'r' => $r,
            ], [
                'Content-Type' => 'application/json',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'custom.calc_sign.heartBeat 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'custom.calc_sign.heartBeat');
    }
}
