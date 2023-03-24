<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Manga;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiManga
{

    /**
     * 签到
     * @return array
     */
    public static function ClockIn(): array
    {
        $url = 'https://manga.bilibili.com/twirp/activity.v1.Activity/ClockIn';
        $payload = [];
        // {"code":0,"msg":"","data":{}}
        // {"code":"invalid_argument","msg":"clockin clockin is duplicate","meta":{"argument":"clockin"}}
        return Request::postJson(true, 'app', $url, Sign::common($payload));
    }


    /**
     * 分享
     * @return array
     */
    public static function ShareComic(): array
    {
        $url = 'https://manga.bilibili.com/twirp/activity.v1.Activity/ShareComic';
        $payload = [];
        // {"code":0,"msg":"","data":{"point":5}}
        // {"code":1,"msg":"","data":{"point":0}}
        // {"code":0, "msg":"今日已分享"}
        return Request::postJson(true, 'app', $url, Sign::common($payload));
    }

    /**
     * 签到信息
     * @return array
     */
    public static function GetClockInInfo(): array
    {
        $url = 'https://manga.bilibili.com/twirp/activity.v1.Activity/GetClockInInfo';
        $payload = [];
        return Request::postJson(true, 'app', $url, Sign::common($payload));
    }
}
