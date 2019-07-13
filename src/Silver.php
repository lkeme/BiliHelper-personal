<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class Silver
{
    public static $lock = 0;
    protected static $task = [];

    public static function run()
    {
        if (self::$lock > time()) {
            return;
        }

        if (!empty(self::$task)) {
            self::pushTask();
        } else {
            self::pullTask();
        }
    }

    protected static function pushTask()
    {
        $payload = [
            'time_end' => self::$task['time_end'],
            'time_start' => self::$task['time_start']
        ];
        $data = Curl::get('https://api.live.bilibili.com/mobile/freeSilverAward', Sign::api($payload));
        $data = json_decode($data, true);

        if ($data['code'] == -800) {
            self::$lock = time() + 12 * 60 * 60;
            Log::warning("领取宝箱失败，{$data['message']}!");
            return;
        }

        if ($data['code'] == -903) {
            Log::warning("领取宝箱失败，{$data['message']}!");
            self::$task = [];
            self::$lock = time() + 60;
            return;
        }

        if (isset($data['code']) && $data['code']) {
            Log::warning("领取宝箱失败，{$data['message']}!");
            self::$lock = time() + 60;
            return;
        }

        Log::notice("领取宝箱成功，Silver: {$data['data']['silver']}(+{$data['data']['awardSilver']})");

        self::$task = [];
        self::$lock = time() + 10;
    }

    protected static function pullTask()
    {
        $payload = [];
        $data = Curl::get('https://api.live.bilibili.com/lottery/v1/SilverBox/getCurrentTask', Sign::api($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code'] == -10017) {
            Log::notice($data['message']);
            self::$lock = time() + 24 * 60 * 60;
            return;
        }

        if (isset($data['code']) && $data['code']) {
            Log::error("check freeSilverCurrentTask failed! Error message: {$data['message']}");
            die();
        }

        Log::info("获得一个宝箱，内含 {$data['data']['silver']} 个瓜子");
        Log::info("开启宝箱需等待 {$data['data']['minute']} 分钟");

        self::$task = [
            'time_start' => $data['data']['time_start'],
            'time_end' => $data['data']['time_end'],
        ];

        self::$lock = time() + $data['data']['minute'] * 60 + 5;
    }
}
