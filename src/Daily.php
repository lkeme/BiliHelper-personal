<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class Daily
{
    public static $lock = 0;

    public static function run()
    {
        if (self::$lock > time()) {
            return;
        }
        self::dailyBag();

        self::$lock = time() + 3600;
    }

    protected static function dailyBag()
    {
        $payload = [];
        $data = Curl::get('https://api.live.bilibili.com/gift/v2/live/receive_daily_bag', Sign::api($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code']) {
            Log::warning('每日礼包领取失败!', ['msg' => $data['message']]);
        } else {
            Log::notice('每日礼包领取成功');
        }
    }

}
