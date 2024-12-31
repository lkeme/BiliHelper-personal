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

namespace Bhp\Api\DynamicSvr;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiDynamicSvr
{
    /**
     * 获取关注Up动态
     * @return mixed
     */
    public static function followUpDynamic(): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/dynamic_new';
        $payload = [
            'uid' => $user['uid'],
            'type_list' => '8,512,4097,4098,4099,4100,4101'
        ];
        $headers = [
            'origin' => 'https://t.bilibili.com',
            'referer' => 'https://t.bilibili.com/pages/nav/index_new'
        ];
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

}

