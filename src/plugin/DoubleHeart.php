<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class DoubleHeart
{
    use TimeLock;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('double_heart')) {
            return;
        }
        self::setPauseStatus();
        self::webHeart();
        self::appHeart();
        self::setLock(5 * 60);
    }

    /**
     * @use Web 心跳
     */
    protected static function webHeart(): void
    {
        User::webGetUserInfo();
        $url = 'https://api.live.bilibili.com/User/userOnlineHeart';
        $payload = [
            'csrf' => getCsrf(),
            'csrf_token' => getCsrf(),
            'room_id' => getConf('room_id', 'global_room'),
            '_' => time() * 1000,
        ];
        $headers = [
            'Referer' => 'https://live.bilibili.com/' . $payload['room_id'],
        ];
        $data = Curl::post('app', $url, $payload, $headers);
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('[PC] 发送在线心跳失败', ['msg' => $data['message']]);
        } else {
            Log::notice('[PC] 发送在线心跳成功');
        }
    }

    /**
     * @use 手机端心跳
     */
    protected static function appHeart(): void
    {
        User::appGetUserInfo();
        $url = 'https://api.live.bilibili.com/mobile/userOnlineHeart';
        $payload = [
            'room_id' => getConf('room_id', 'global_room'),
        ];
        $data = Curl::post('app', $url, Sign::common($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('[APP] 发送在线心跳失败', ['msg' => $data['message']]);
        } else {
            Log::notice('[APP] 发送在线心跳成功');
        }
    }
}
