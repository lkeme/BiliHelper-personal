<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;


use BiliHelper\Util\TimeLock;
use BiliHelper\Util\XliveHeartBeat;

class SmallHeart
{
    use TimeLock;
    use XliveHeartBeat;


    private static $fans_medals = []; // 全部勋章
    private static $grey_fans_medals = []; // 灰色勋章
    private static $metal_lock = 0; // 勋章时间锁
    private static $interval = 60; // 每次跳动时间
    private static $total_time = 0;
    private static $metal = null;

    public static function run()
    {
        if (getenv('USE_HEARTBEAT') == 'false') {
            return;
        }

        if (self::$metal_lock < time()) {
            self::polishMetal();
            self::$metal_lock = time() + 8 * 60 * 60;
        }
        if (self::getLock() < time()) {
            self::heartBeat();
            if (self::$total_time >= 12000) {
                self::$total_time = 0;
                self::$metal = null;
                self::setLock(self::timing(2));
            } else {
                self::setLock(self::$interval);
            }
        }
    }


    /**
     * @use 勋章处理
     */
    private static function polishMetal()
    {
        // 灰色勋章
        self::fetchGreyMedalList();
        if (empty(self::$grey_fans_medals)) {
            return;
        }
        // 小心心
        $bag_list = Live::fetchBagListByGift('小心心', 30607);
        if (empty($bag_list)) {
            return;
        }
        // 擦亮勋章
        foreach ($bag_list as $gift) {
            for ($num = 1; $num <= $gift['gift_num']; $num++) {
                $grey_fans_medal = array_shift(self::$grey_fans_medals);
                // 为空
                if (is_null($grey_fans_medal)) break;
                // 擦亮
                Live::sendGift($grey_fans_medal, $gift, 1);
            }
        }
    }


    /**
     * @use 心跳处理
     */
    private static function heartBeat()
    {
        if (empty(self::$fans_medals)) {
            return;
        }
        if (is_null(self::$metal)){
            self::$metal = self::$fans_medals[array_rand(self::$fans_medals)];
        }
        $interval = self::xliveHeartBeatTask(self::$metal['roomid'], 999, 999);
        if ($interval != 0) {
            self::$total_time += $interval;
        }
        self::$interval = $interval == 0 ? 60 : $interval;
    }


    /**
     * @use 获取灰色勋章列表(过滤无勋章或已满)
     */
    private static function fetchGreyMedalList()
    {
        $data = Live::fetchMedalList();
        $user_info = User::parseCookies();
        foreach ($data as $vo) {
            // 过滤主站勋章
            if (!isset($vo['roomid'])) continue;
            // 过滤自己勋章
            if ($vo['target_id'] == $user_info['uid']) continue;
            // 所有
            self::$fans_medals[] = [
                'uid' => $vo['target_id'],
                'roomid' => $vo['roomid'],
            ];
            //  灰色
            if ($vo['medal_color_start'] == 12632256 && $vo['medal_color_end'] == 12632256 && $vo['medal_color_border'] == 12632256) {
                self::$grey_fans_medals[] = [
                    'uid' => $vo['target_id'],
                    'roomid' => $vo['roomid'],
                ];
            }
        }
    }

}