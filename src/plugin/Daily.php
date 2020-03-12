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

class Daily
{
    use TimeLock;

    public static function run()
    {
        if (self::getLock() > time()) {
            return;
        }
        self::dailyBag();
        self::setLock(8 * 60 * 60);
    }

    /**
     * @use 领取每日包裹
     */
    private static function dailyBag()
    {
        $url = 'https://api.live.bilibili.com/gift/v2/live/receive_daily_bag';
        $payload = [];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('每日礼包领取失败!', ['msg' => $data['message']]);
        } else {
            Log::notice('每日礼包领取成功');
        }
    }

}
