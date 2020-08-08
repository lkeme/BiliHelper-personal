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

class ActivityLottery
{
    use TimeLock;

    private static $activity_infos = [
        '2020SummerMusic' => [
            'sid' => 'dd83a687-c800-11ea-8597-246e966235d8',
            'action_types' => [3, 4], // 4 关注  3 分享
            'referer' => 'https://www.bilibili.com/blackboard/2020SummerMusic.html',
            'expired_time' => 1599318000, // 2020-09-05 23:00:00
            'draw_times' => 3,
        ],
    ];

    public static function run()
    {
        if (self::getLock() > time() || getenv('USE_ACTIVITY') == 'false') {
            return;
        }
        self::workTask();
        self::setLock(self::timing(5) + mt_rand(1, 180));
    }


    /**
     * @use 运行任务
     */
    private static function workTask()
    {
        foreach (self::$activity_infos as $title => $activity) {
            // 过期
            if ($activity['expired_time'] < time()) {
                Log::info('跳过');
                continue;
            }
            Log::info("启动 {$title} 抽奖任务");
            self::initTimes($activity['sid'], $activity['referer']);
            foreach ($activity['action_types'] as $action_type) {
                sleep(1);
                self::addTimes($activity['sid'], $activity['referer'], $action_type);
            }
            foreach (range(1, $activity['draw_times']) as $num) {
                sleep(5);
                self::doLottery($activity['sid'], $activity['referer'], $num);
            }
        }
    }


    /**
     * @use 获取抽奖机会
     * @param string $sid
     * @param string $referer
     * @return bool
     */
    private static function initTimes(string $sid, string $referer): bool
    {
        $url = 'https://api.bilibili.com/x/activity/lottery/mytimes';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $referer
        ];
        $payload = [
            'sid' => $sid,
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        Log::info("获取抽奖机会 {$raw}");
        // {"code":0,"message":"0","ttl":1,"data":{"times":2}}
        if ($de_raw['code'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * @use 增加抽奖机会
     * @param string $sid
     * @param string $referer
     * @param int $action_type
     * @return bool
     */
    private static function addTimes(string $sid, string $referer, int $action_type): bool
    {
        $url = 'https://api.bilibili.com/x/activity/lottery/addtimes';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $referer
        ];
        $user_info = User::parseCookies();
        $payload = [
            'sid' => $sid,
            'action_type' => $action_type,
            'csrf' => $user_info['token']
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        Log::info("增加抽奖机会#{$action_type} {$raw}");
        // {"code":0,"message":"0","ttl":1}
        if ($de_raw['code'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * @use 开始抽奖
     * @param string $sid
     * @param string $referer
     * @param int $num
     * @return bool
     */
    private static function doLottery(string $sid, string $referer, int $num): bool
    {
        $url = 'https://api.bilibili.com/x/activity/lottery/do';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $referer
        ];
        $user_info = User::parseCookies();
        $payload = [
            'sid' => $sid,
            'type' => 1,
            'csrf' => $user_info['token']
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        Log::notice("开始抽奖#{$num} {$raw}");
        // {"code":0,"message":"0","ttl":1,"data":[{"id":0,"mid":4133274,"num":1,"gift_id":1152,"gift_name":"硬币x6","gift_type":0,"img_url":"https://i0.hdslb.com/bfs/activity-plat/static/b6e956937ee4aefd1e19c01283145fc0/JQ9Y9-KCm_w96_h102.png","type":5,"ctime":1596255796,"cid":0}]}
        if ($de_raw['code'] == 0) {
            return true;
        }
        return false;
    }


}