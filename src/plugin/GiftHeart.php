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

class GiftHeart
{
    use TimeLock;

    public static function run()
    {
        if (self::getLock() > time()) {
            return;
        }
        if (self::giftHeart()) {
            self::setLock(60 * 60);
            return;
        }
        self::setLock(5 * 60);
    }


    /**
     * @use 礼物心跳
     * @return bool
     */
    private static function giftHeart(): bool
    {
        $payload = [
            'roomid' => getenv('ROOM_ID'),
        ];
        $raw = Curl::get('https://api.live.bilibili.com/gift/v2/live/heart_gift_receive', Sign::api($payload));
        $de_raw = json_decode($raw, true);

        if ($de_raw['code'] == -403) {
            Log::info($de_raw['msg']);
            $payload = [
                'ruid' => 17561885,
            ];
            Curl::get('https://api.live.bilibili.com/eventRoom/index', Sign::api($payload));
            return true;
        }

        if ($de_raw['code'] != 0) {
            Log::warning($de_raw['msg']);
            return false;
        }

        if ($de_raw['data']['heart_status'] == 0) {
            Log::info('没有礼物可以领了呢!');
            return true;
        }

        if (isset($de_raw['data']['gift_list'])) {
            foreach ($de_raw['data']['gift_list'] as $vo) {
                Log::info("{$de_raw['msg']}，礼物 {$vo['gift_name']} ({$vo['day_num']}/{$vo['day_limit']})");
            }
            return false;
        }
    }

}