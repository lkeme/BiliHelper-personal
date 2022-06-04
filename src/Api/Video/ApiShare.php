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

namespace Bhp\Api\Video;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiShare
{
    /**
     * @use 分享视频
     * @param string $aid
     * @param string $cid
     * @return array
     */
    public static function share(string $aid): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/web-interface/share/add';
        //
        $payload = [
            'aid' => $aid,
            'jsonp' => "jsonp",
            'csrf' => $user['csrf'],
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'Referer' => "https://www.bilibili.com/video/av$aid",
        ];
        // {"code":0,"message":"0","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

}