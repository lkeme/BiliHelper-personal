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

namespace Bhp\Api\Api\X\Player;

use Bhp\Request\Request;

class ApiPlayer
{
    public static function pageList(string $aid): array
    {
        $url = 'https://api.bilibili.com/x/player/pagelist';
        $payload = [
            'aid' => $aid,
        ];
        // {"code":-404,"message":"啥都木有","ttl":1}
        // {"code":0,"message":"0","ttl":1,"data":[{"cid":123,"page":1,"from":"vupload","part":"","duration":2055,"vid":"","weblink":"","dimension":{"width":480,"height":360,"rotate":0}}]}
        return Request::getJson(true, 'other', $url, $payload);
    }

}
