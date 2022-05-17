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

class DailyBag
{
    use TimeLock;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('daily_bag')) {
            return;
        }
        self::dailyBagPC();
        self::dailyBagAPP();
        self::setLock(12 * 60 * 60);
    }

    /**
     * @use 领取每日包裹PC
     */
    private static function dailyBagPC(): void
    {
        sleep(1);
        $url = 'https://api.live.bilibili.com/gift/v2/live/receive_daily_bag';
        $payload = [];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('[PC] 日常/周常礼包领取失败', ['msg' => $data['message']]);
        } else {
            Log::notice('[PC] 日常/周常礼包领取成功');
        }
    }

    /**
     * @use 领取每日包裹APP
     */
    private static function dailyBagAPP(): void
    {
        sleep(1);
        $url = 'https://api.live.bilibili.com/AppBag/sendDaily';
        $payload = [];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('[APP] 日常/周常礼包领取失败', ['msg' => $data['message']]);
        } else {
            Log::notice('[APP] 日常/周常礼包领取成功');
        }
    }

}
