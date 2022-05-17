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

    private static array $fans_medals = []; // 全部勋章
    private static int $metal_lock = 0; // 勋章时间锁
    private static int $interval = 60; // 每次跳动时间
    private static int $total_time = 0;
    private static array|null $metal = null;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (!getEnable('small_heart')) {
            return;
        }
        if (self::$metal_lock < time()) {
            self::fetchMedalList();
            self::$metal_lock = time() + 12 * 60 * 60;
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
     * @use 心跳处理
     */
    private static function heartBeat(): void
    {
        if (empty(self::$fans_medals)) {
            return;
        }
        if (is_null(self::$metal)) {
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
    private static function fetchMedalList(): void
    {
        $data = Live::fetchMedalList();
        foreach ($data as $vo) {
            // 过滤主站勋章
            if (!isset($vo['roomid']) || $vo['roomid'] == 0) continue;
            // 过滤自己勋章
            if ($vo['target_id'] == getUid()) continue;
            // 所有
            self::$fans_medals[] = [
                'uid' => $vo['target_id'],
                'roomid' => $vo['roomid'],
            ];
        }
    }

}