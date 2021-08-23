<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Script;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;

class User
{

    /**
     * @use 登录
     * @return bool
     */
    public static function login(): bool
    {
        if (getAccessToken() == '' || getCookie() == '' || getUid() == '' || getCsrf() == '') {
            Log::error('用户信息不全，请默认模式登录后使用。');
            die();
        }
        $data = User::userInfo();
        if ($data['code'] == 0 && $data['data']['isLogin']) {
            $nav = $data['data'];
            $level = $nav['level_info'];
            Log::notice("登录成功 Uname={$nav['uname']} Uid={$nav['mid']} Lv={$level['current_level']} ({$level['current_exp']}/{$level['current_min']})");
        } else {
            Log::error("登录失败 CODE -> {$data['code']} MSG -> {$data['message']} ");
            die();
        }
        return true;
    }

    /**
     * @use 用户
     * @return mixed
     */
    public static function userInfo(): mixed
    {
        $url = 'https://api.bilibili.com/x/web-interface/nav';
        $payload = [];
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => 'https://space.bilibili.com/' . getUid(),
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        return json_decode($raw, true);
    }


}