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

namespace Bhp\Api\Api\X\Activity;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiActivity
{
    /**
     * @param array $info
     * @param int $num
     * @return array
     */
    public static function doLottery(array $info, int $num = 1): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/lottery/do';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $info['url'],
        ];
        $payload = [
            'sid' => $info['sid'],
            'num' => $num,
            'csrf' => $user['csrf'],
        ];
        // {"code":0,"message":"0","ttl":1,"data":[{"id":0,"mid":123,"ip":0,"num":1,"gift_id":0,"gift_name":"未中奖0","gift_type":0,"img_url":"","type":1,"ctime":123,"cid":0,"extra":{},"award_info":null,"order_no":""}]}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * @param array $info
     * @param int $action_type 4 关注  3 分享
     * @return array
     */
    public static function addTimes(array $info, int $action_type = 3): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/lottery/addtimes';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $info['url'],
        ];
        $payload = [
            'sid' => $info['sid'],
            'action_type' => $action_type,
            'csrf' => $user['csrf'],
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"add_num":1}}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * @param array $info
     * @return array
     */
    public static function myTimes(array $info): array
    {
        $url = 'https://api.bilibili.com/x/lottery/mytimes';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $info['url'],
        ];
        $payload = [
            'sid' => $info['sid'],
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"times":1,"lottery_type":0,"points":0,"points_per_time":0,"intergral":null}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }


}
