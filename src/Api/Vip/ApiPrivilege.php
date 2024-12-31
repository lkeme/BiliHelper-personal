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
use Bhp\User\User;

class ApiPrivilege
{
    /**
     * 获取我的大会员权益列表
     * @return array
     */
    public static function my(): array
    {
        $url = 'https://api.bilibili.com/x/vip/privilege/my';
        $payload = [];
        $headers = [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/big/myPackage',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"list":[{"type":1,"state":0,"expire_time":1622476799},{"type":2,"state":0,"expire_time":1622476799}]}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 领取权益
     * @param int $type
     * @return array
     */
    public static function receive(int $type): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/vip/privilege/receive';
        $payload = [
            'type' => $type,
            'csrf' => $user['csrf'],
        ];
        $headers = [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/big/myPackage',
        ];
        // {"code":0,"message":"0","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

}
