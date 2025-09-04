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

class ApiPrivilegeAssets
{
    /**
     * @var array|string[]
     */
    protected static array $headers = [
        'Referer' => 'https://big.bilibili.com/mobile/cardBag?closable=1&navhide=1&tab=all'
    ];

    /**
     * 获取大会员权益列表
     * @return array
     */
    public static function list(): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/vip/privilege_assets/list';
        $payload = [
            'csrf' => $user['csrf'],
        ];
        $headers = array_merge([], self::$headers);
        return Request::getJson(true, 'app', $url, Sign::common($payload), $headers);
    }

    /**
     * 兑换大会员权益
     * @param string $token
     * @return array
     */
    public static function exchange(string $token): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/vip/privilege_assets/exchange';
        $payload = [
            'token' => $token,
            'csrf' => $user['csrf'],
        ];
        $headers = array_merge([], self::$headers);
        return Request::postJson(true, 'app', $url, Sign::common($payload), $headers);
    }
}
