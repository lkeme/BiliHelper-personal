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

namespace Bhp\Api\Vip;

use Bhp\Request\Request;
use Bhp\Sign\Sign;
use Bhp\User\User;

class ApiVipCenter
{
    /**
     * @var array|string[]
     */
    protected static array $headers = [
        'Referer' => 'https://big.bilibili.com/mobile/index'
    ];

    /**
     * 大会员中心
     * @return array
     */
    public static function v2(): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/vip/web/vip_center/v2';
        $payload = [
            'csrf' => $user['csrf'],
        ];
        $headers = array_merge([], self::$headers);
        return Request::getJson(true, 'app', $url, Sign::common($payload), $headers);
    }
}
