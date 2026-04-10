<?php declare(strict_types=1);

namespace Bhp\Api\Pay;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiWallet extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserWallet(): array
    {
        return $this->decodeGet('pc', 'https://pay.bilibili.com/payplatform/getUserWalletInfo', [], [
            'origin' => 'https://pay.bilibili.com',
            'referer' => 'https://pay.bilibili.com/paywallet-fe/bb_balance.html',
        ], 'pay.wallet.get_user_wallet_info');
    }
}
