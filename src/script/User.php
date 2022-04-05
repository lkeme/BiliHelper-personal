<?php

/**
 * 
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 *
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *  &   ／l、
 *    （ﾟ､ ｡ ７
 *   　\、ﾞ ~ヽ   *
 *   　じしf_, )ノ
 *
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