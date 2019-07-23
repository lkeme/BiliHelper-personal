<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class Winning
{
    public static $lock = 0;

    // RUN
    public static function run()
    {
        // 活动统一
        UnifyRaffle::resultWeb();

        // 实物
        self::winningRecords();
    }

    // 中奖记录
    protected static function winningRecords()
    {
        if (self::$lock > time()) {
            return;
        }
        self::$lock = time() + 24 * 60 * 60;

        $payload = [
            'page' => '1',
            'month' => '',
        ];
        $raw = Curl::post('https://api.live.bilibili.com/lottery/v1/award/award_list', Sign::api($payload));
        $de_raw = json_decode($raw, true);
        $month = $de_raw['data']['month_list'][0]['Ym'];

        // TODO
        $payload = [
            'page' => '1',
            'month' => $month,
        ];
        $raw = Curl::post('https://api.live.bilibili.com/lottery/v1/award/award_list', Sign::api($payload));
        $de_raw = json_decode($raw, true);
        // 没有记录
        if (empty($de_raw['data']['list'])) {
            return;
        }

        $init_time = strtotime(date("y-m-d h:i:s")); //当前时间
        foreach ($de_raw['data']['list'] as $gift) {
            $next_time = strtotime($gift['create_time']);  //礼物时间
            $day = ceil(($init_time - $next_time) / 86400);  //60s*60min*24h

            if ($day <= 2 && $gift['update_time'] == '') {
                $data_info = '您在: ' . $gift['create_time'] . '抽中[' . $gift['gift_name'] . 'X' . $gift['gift_num'] . ']未查看!';
                Log::notice($data_info);
                // TODO 推送 log
            }
        }
        return;
    }
}