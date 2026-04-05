<?php declare(strict_types=1);

namespace Bhp\Api\XLive\Revenue\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiWallet
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function apCenterList(): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.live.bilibili.com/xlive/revenue/v1/wallet/apcenterList', [
                'page' => 1,
            ], [
                'origin' => 'https://link.bilibili.com',
                'referer' => 'https://link.bilibili.com/p/center/index',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.revenue.ap_center_list 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.revenue.ap_center_list');
    }
}
