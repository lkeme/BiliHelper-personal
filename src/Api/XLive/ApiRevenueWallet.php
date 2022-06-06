<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\XLive;

use Bhp\Request\Request;
use Bhp\Sign\Sign;
use Bhp\User\User;

class ApiRevenueWallet
{
    /**
     * @use app银瓜子兑换硬币
     * @return array
     */
    public static function appSilver2coin(): array
    {
        $url = 'https://api.live.bilibili.com/AppExchange/silver2coin';
        $payload = [];
        return Request::postJson(true, 'app', $url, Sign::common($payload));
    }

    /**
     * @use pc银瓜子兑换硬币
     * @return array
     */
    public static function pcSilver2coin(): array
    {
        $user = User::parseCookie();
        // $url = "https://api.live.bilibili.com/exchange/silver2coin";
        // $url = "https://api.live.bilibili.com/pay/v1/Exchange/silver2coin";
        $url = "https://api.live.bilibili.com/xlive/revenue/v1/wallet/silver2coin";
        $payload = [
            'csrf_token' => $user['csrf'],
            'csrf' => $user['csrf'],
            'visit_id' => ''
        ];
        return Request::postJson(true, 'pc', $url, $payload);
    }


}
 