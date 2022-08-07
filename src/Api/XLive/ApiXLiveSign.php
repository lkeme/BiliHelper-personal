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

class ApiXLiveSign
{
    /**
     * 签到信息
     * @return array
     */
    public static function webGetSignInfo(): array
    {
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/v1/sign/WebGetSignInfo';
        $payload = [];
        $headers = [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index'
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"text":"","specialText":"","status":0,"allDays":30,"curMonth":6,"curYear":2022,"curDay":4,"curDate":"2022-6-4","hadSignDays":0,"newTask":0,"signDaysList":[],"signBonusDaysList":[]}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 签到
     * @return array
     */
    public static function doSign(): array
    {
        $url = 'https://api.live.bilibili.com/sign/doSign';
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/v1/sign/DoSign';
        $payload = [];
        $headers = [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index'
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"text":"3000点用户经验,2根辣条","specialText":"再签到4天可以获得666银瓜子","allDays":30,"hadSignDays":1,"isBonusDay":0}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }
}