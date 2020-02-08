<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;
use BiliHelper\Core\Config;


class User
{


    /**
     * @use 实名检测
     * @return bool
     */
    public static function realNameCheck(): bool
    {
        $payload = [];
        $raw = Curl::get('https://account.bilibili.com/identify/index', Sign::api($payload));
        $de_raw = json_decode($raw, true);
        //检查有没有名字，没有则没实名
        if (!$de_raw['data']['memberPerson']['realname']) {
            return false;
        }
        return true;
    }


    /**
     * @use 是否是老爷
     * @return bool
     */
    public static function isMaster(): bool
    {
        $payload = [
            'ts' => Live::getMillisecond(),
        ];
        $raw = Curl::get('https://api.live.bilibili.com/User/getUserInfo', Sign::api($payload));
        $de_raw = json_decode($raw, true);
        if ($de_raw['msg'] == 'ok') {
            if ($de_raw['data']['vip'] || $de_raw['data']['svip']) {
                return true;
            }
        }
        return false;
    }


    /**
     * @use 用户名写入
     * @return bool
     */
    public static function userInfo(): bool
    {
        $payload = [
            'ts' => Live::getMillisecond(),
        ];
        $raw = Curl::get('https://api.live.bilibili.com/User/getUserInfo', Sign::api($payload));
        $de_raw = json_decode($raw, true);

        if (getenv('APP_UNAME') != "") {
            return true;
        }
        if ($de_raw['msg'] == 'ok') {
            Config::put('APP_UNAME', $de_raw['data']['uname']);
            return true;
        }
        return false;
    }


    /**
     * @use 转换信息
     * @return array
     */
    public static function parseCookies(): array
    {
        $cookies = getenv('COOKIE');
        preg_match('/bili_jct=(.{32})/', $cookies, $token);
        $token = isset($token[1]) ? $token[1] : '';
        preg_match('/DedeUserID=(\d+)/', $cookies, $uid);
        $uid = isset($uid[1]) ? $uid[1] : '';
        preg_match('/DedeUserID__ckMd5=(.{16})/', $cookies, $sid);
        $sid = isset($sid[1]) ? $sid[1] : '';
        return [
            'token' => $token,
            'uid' => $uid,
            'sid' => $sid,
        ];
    }
}