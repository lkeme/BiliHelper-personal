<?php declare(strict_types=1);

namespace Bhp\Api\XLive\Revenue\V1;

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
    public function apCenterList(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/xlive/revenue/v1/wallet/apcenterList', [
            'page' => 1,
        ], [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index',
        ], 'xlive.revenue.ap_center_list');
    }
}
