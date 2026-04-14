<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Pay;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiPay extends AbstractApiClient
{
    /**
     * 初始化 ApiPay
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function gold(int $num): array
    {
        $payload = [
            'platform' => 'pc',
            'pay_bp' => $num * 1000,
            'context_id' => 1,
            'context_type' => 11,
            'goods_id' => 1,
            'goods_num' => $num,
            'csrf_token' => $this->request()->csrfValue(),
            'csrf' => $this->request()->csrfValue(),
            'visit_id' => '',
        ];

        return $this->decodePost('pc', 'https://api.live.bilibili.com/xlive/revenue/v1/order/createOrder', $payload, [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index',
        ], 'pay.gold');
    }

    /**
     * @return array<string, mixed>
     */
    public function battery(int $up_mid, int $num = 5): array
    {
        $payload = [
            'bp_num' => $num,
            'is_bp_remains_prior' => true,
            'up_mid' => $up_mid,
            'otype' => 'up',
            'oid' => $up_mid,
            'csrf' => $this->request()->csrfValue(),
        ];

        return $this->decodePost('pc', 'https://api.bilibili.com/x/ugcpay/web/v2/trade/elec/pay/quick', $payload, [], 'pay.battery');
    }
}
