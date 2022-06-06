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
        // {"code":403,"data":{"coin":0,"gold":0,"silver":0,"tid":""},"message":"银瓜子余额不足"}
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
        // {"code":403,"data":{"coin":0,"gold":0,"silver":0,"tid":""},"message":"银瓜子余额不足"}
        return Request::postJson(true, 'pc', $url, $payload);
    }

    /**
     * @use 钱包状态
     * @return array
     */
    public static function getStatus(): array
    {
        $url = "https://api.live.bilibili.com/xlive/revenue/v1/wallet/getStatus";
        $payload = [];
        // {"code":0,"message":"0","ttl":1,"data":{"silver":1111,"gold":0,"coin":11,"bp":11,"coin_2_silver_left":50,"silver_2_coin_left":1,"num":50,"status":1,"vip":1}}
        return Request::getJson(true, 'pc', $url, $payload);
    }

    /**
     * @use 我的钱包
     * @return array
     */
    public static function myWallet(): array
    {
        $url = 'https://api.live.bilibili.com/xlive/revenue/v1/wallet/myWallet';
        $payload = [
            'need_bp' => 1,
            'need_metal' => 1,
            'platform' => 'pc'
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"gold":0,"silver":111,"bp":"0","metal":111,"need_use_new_bp":true,"ios_bp":0,"common_bp":0,"new_bp":"0","bp_2_gold_amount":0}}
        return Request::getJson(true, 'pc', $url, $payload);

    }


}
 