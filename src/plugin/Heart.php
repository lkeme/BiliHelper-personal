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

        self::pc();
        self::mobile();

        self::setLock(5 * 60);
    }

    /**
     * @use pc端心跳
     */
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

    /**
     * @use 手机端心跳
     */
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
