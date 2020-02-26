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

class Heart
{
    use TimeLock;

    public static function run()
    {
        if (self::getLock() > time()) {
            return;
        }
        self::webHeart();
        self::appHeart();
        self::setLock(5 * 60);
    }

    /**
     * @use Web 心跳
     */
    protected static function webHeart()
    {
        User::webGetUserInfo();
        $payload = [
            'room_id' => getenv('ROOM_ID'),
        ];
        $data = Curl::post('https://api.live.bilibili.com/relation/v1/Feed/heartBeat', Sign::api($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('WEB端 发送心跳异常!', ['msg' => $data['message']]);
        } else {
            Log::info('WEB端 发送心跳正常!');
        }
    }

    /**
     * @use 手机端心跳
     */
    protected static function appHeart()
    {
        User::appGetUserInfo();
        $payload = [
            'room_id' => getenv('ROOM_ID'),
        ];
        $data = Curl::post('https://api.live.bilibili.com/mobile/userOnlineHeart', Sign::api($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('APP端 发送心跳异常!', ['msg' => $data['message']]);
        } else {
            Log::info('APP端 发送心跳正常!');
        }
    }
}
