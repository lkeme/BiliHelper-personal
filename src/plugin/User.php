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
     * @throws \Exception
     */
    public static function realNameCheck(): bool
    {
        $url = 'https://account.bilibili.com/identify/index';
        $payload = [];
        $raw = Curl::get('pc', $url, $payload);
        $de_raw = json_decode($raw, true);
        //检查有没有名字，没有则没实名
        if (!$de_raw['data']['memberPerson']['realname']) {
            return false;
        }
        return true;
    }


    /**
     * @use 老爷检测
     * @return bool
     * @throws \Exception
     */
    public static function isMaster(): bool
    {
        $data = self::getUserInfo();
        if ($data['msg'] == 'ok') {
            if ($data['data']['vip'] || $data['data']['svip']) {
                return true;
            }
        }
        return false;
    }


    /**
     * @use 写入用户名
     * @return bool
     * @throws \Exception
     */
    public static function userInfo(): bool
    {
        $data = self::getUserInfo();
        if (getenv('APP_UNAME') != "") {
            return true;
        }
        if ($data['msg'] == 'ok') {
            Config::put('APP_UNAME', $data['data']['uname']);
            return true;
        }
        return false;
    }


    /**
     * @use UserInfo
     * @return array
     */
    public static function getUserInfo(): array
    {
        $url = 'https://api.live.bilibili.com/User/getUserInfo';
        $payload = [
            'ts' => Live::getMillisecond(),
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        return json_decode($raw, true);
    }

    /**
     * @use Web User
     * @param null $room_id
     * @return mixed
     * @throws \Exception
     */
    public static function webGetUserInfo($room_id = null)
    {
        $url = 'https://api.live.bilibili.com/xlive/web-room/v1/index/getInfoByUser';
        $payload = [
            'room_id' => $room_id ?? getenv('ROOM_ID')
        ];
        $raw = Curl::get('pc', $url, $payload);
        return json_decode($raw, true);;
    }


    /**
     * @use App User
     * @param null $room_id
     * @return mixed
     */
    public static function appGetUserInfo($room_id = null)
    {
        $url = 'https://api.live.bilibili.com/xlive/app-room/v1/index/getInfoByUser';
        $payload = [
            'room_id' => $room_id ?? getenv('ROOM_ID')
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        return json_decode($raw, true);;
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