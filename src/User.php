<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class User
{
    // RUN
    public static function run()
    {
    }

    // 实名检测
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

    // 老爷检测
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

    // 用户名写入
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
            File::writeNewEnvironmentFileWith('APP_UNAME', $de_raw['data']['uname']);
            return true;
        }
        return false;
    }

    //转换信息
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