<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2024 ~ 2025
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Pay;

use Bhp\Request\Request;
use Bhp\User\User;
use Bhp\Util\Common\Common;

class ApiPay
{

    /**
     * 金瓜子
     * @param int $num
     * @return array
     */
    public static function gold(int $num): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.live.bilibili.com/xlive/revenue/v1/order/createOrder';
        $payload = [
            'platform' => 'pc',
            'pay_bp' => $num * 1000, // 瓜子数量
            'context_id' => 1, // 直播间
            'context_type' => 11,
            'goods_id' => 1, // 商品ID
            'goods_num' => $num, // B币数量
            'csrf_token' => $user['csrf'],
            'csrf' => $user['csrf'],
            'visit_id' => '',
        ];
        $headers = [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index'
        ];
        // {"code":1300014,"message":"b币余额不足","ttl":1,"data":null}
        // {"code":0,"message":"0","ttl":1,"data":{"status":2,"order_id":"1234171134577071132741234","gold":0,"bp":5000}}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 电池
     * @param int $up_mid
     * @param int $num
     * @return array
     */
    public static function battery(int $up_mid, int $num = 5): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/ugcpay/web/v2/trade/elec/pay/quick';
        $payload = [
            'bp_num' => $num, // 数量
            'is_bp_remains_prior' => true, // 是否优先扣除B币余额
            'up_mid' => $up_mid, // 目标UID
            'otype' => 'up', // 来源 up：空间充电 archive：视频充电
            'oid' => $up_mid, // 目标UID or 稿件avid
            'csrf' => $user['csrf']
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"mid":12324,"up_mid":1234,"order_no":"PAY4567","bp_num":"5","exp":5,"status":4,"msg":""}}
        // {"code":0,"message":"0","ttl":1,"data":{"mid":12324,"up_mid":1234,"order_no":"ABCD","bp_num":2,"exp":2,"status":4,"msg":""}}
        return Request::postJson(true, 'pc', $url, $payload);
    }


}
