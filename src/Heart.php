<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class Heart
{
    public static $lock = 0;

    public static function run()
    {
        if (self::$lock > time()) {
            return;
        }

        self::pc();
        self::mobile();

        self::$lock = time() + 300;
    }

    protected static function pc()
    {
        $payload = [
            'room_id' => getenv('ROOM_ID'),
        ];
        $data = Curl::post('https://api.live.bilibili.com/User/userOnlineHeart', Sign::api($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('WEB端 直播间心跳停止惹～', ['msg' => $data['message']]);
        } else {
            Log::info('WEB端 发送心跳正常!');
        }
    }

    protected static function mobile()
    {
        $payload = [
            'room_id' => getenv('ROOM_ID'),
        ];
        $data = Curl::post('https://api.live.bilibili.com/mobile/userOnlineHeart', Sign::api($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('APP端 直播间心跳停止惹～', ['msg' => $data['message']]);
        } else {
            Log::info('APP端 发送心跳正常!');
        }
    }
}
