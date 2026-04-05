<?php declare(strict_types=1);

namespace Bhp\Api\Pay;

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
    public function getUserWallet(): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://pay.bilibili.com/payplatform/getUserWalletInfo', [], [
                'origin' => 'https://pay.bilibili.com',
                'referer' => 'https://pay.bilibili.com/paywallet-fe/bb_balance.html',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'pay.wallet.get_user_wallet_info 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'pay.wallet.get_user_wallet_info');
    }
}
