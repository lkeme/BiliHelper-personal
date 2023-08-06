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

namespace Bhp\Api\Api\X\Relation;

use Bhp\Request\Request;
use Bhp\Sign\Sign;
use Bhp\User\User;

class ApiRelation
{
    /**
     * 关注列表
     * @param int $pn
     * @param int $ps
     * @param string $order
     * @param string $order_type
     * @return array
     */
    public static function followings(int $pn = 1, int $ps = 20, string $order = 'desc', string $order_type = 'order_type'): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/relation/followings';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$user['uid']}/fans/follow"
        ];
        $payload = [
            'vmid' => $user['uid'],
            'pn' => $pn,
            'ps' => $ps,
            'order' => $order,
            'order_type' => $order_type,
        ];
        //
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }


    /**
     * 获取关注分组列表
     * @return array
     */
    public static function tags(): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/relation/tags';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$user['uid']}/fans/follow"
        ];
        $payload = [];
        //
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 获取关注分组列表
     * @param int $tag_id
     * @param int $pn
     * @param int $ps
     * @return array
     */
    public static function tag(int $tag_id, int $pn = 1, int $ps = 20): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/relation/tag';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$user['uid']}/fans/follow"
        ];
        $payload = [
            'mid' => $user['uid'],
            'tagid' => $tag_id,
            'pn' => $pn,
            'ps' => $ps,
        ];
        //
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }


    /**
     * 取关
     * @param int $uid
     * @return array
     */
    public static function modify(int $uid): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/relation/modify';
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$user['uid']}/fans/follow"
        ];
        $payload = [
            'fid' => $uid,
            'act' => 2,
            're_src' => 11,
            'csrf' => $user['csrf'],
        ];
        //
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }
}
