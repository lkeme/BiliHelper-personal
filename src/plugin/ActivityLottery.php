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
use BiliHelper\Util\AllotTasks;
use BiliHelper\Util\TimeLock;

class ActivityLottery
{
    use TimeLock;
    use AllotTasks;

    private static string $repository = APP_DATA_PATH . 'activity_infos.json';

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('main_activity')) {
            return;
        }
        self::allotTasks();
        if (self::workTask()) {
            self::setLock(5 * 60);
        } else {
            self::setLock(self::timing(5) + mt_rand(1, 180));
        }
    }

    /**
     * @use 分配任务
     * @return bool
     */
    private static function allotTasks(): bool
    {
        if (self::$work_status['work_updated'] == date("Y/m/d")) {
            return false;
        }
        $parser = self::loadJsonData();
        foreach ($parser->get('data', []) as $act) {
            // todo 优化处理
            $act = json_decode(json_encode($act));
            // 活动无效
            if (is_null($act->sid)) {
                continue;
            }
            // 活动实效过期
            if (strtotime($act->expire_at) < time()) {
                continue;
            }
            // init
            if ($act->login == 'true') {
                self::pushTask('login', $act);
            }
            // follow
            if ($act->follow == 'true') {
                self::pushTask('follow', $act);
            }
            // share
            if ($act->share == 'true') {
                self::pushTask('share', $act);
            }
            // draw_times
            $arr = range(1, $act->draw_times);
            foreach ($arr as $ignored) {
                self::pushTask('draw', $act);
            }
        }
        self::$work_status['work_updated'] = date("Y/m/d");
        Log::info('活动抽奖任务分配完成 ' . count(self::$tasks) . ' 个任务待执行');
        return true;
    }

    /**
     * @use 执行任务
     * @return bool
     */
    private static function workTask(): bool
    {
        if (self::$work_status['work_completed'] == date("Y/m/d")) {
            return false;
        }
        $task = self::pullTask();
        // 所有任务完成 标记
        if (!$task) {
            self::$work_status['work_completed'] = date("Y/m/d");
            return false;
        }
        Log::info("执行 {$task['act']->title} #{$task['operation']} 任务");
        // 执行任务
        switch ($task['operation']) {
            case 'login':
                self::initTimes($task['act']->sid, $task['act']->url);
                break;
            case 'follow':
                self::addTimes($task['act']->sid, $task['act']->url, 4);
                break;
            case 'share':
                self::addTimes($task['act']->sid, $task['act']->url, 3);
                break;
            case 'draw':
                // 有抽奖机会才抽奖
                if (self::initTimes($task['act']->sid, $task['act']->url, false)) {
                    self::doLottery($task['act']->sid, $task['act']->url, 0);
                }
                break;
            default:
                Log::info("当前 {$task['act']->title} #{$task['operation']} 任务不存在哦");
                break;
        }
        return true;
    }

    /**
     * @use 获取抽奖机会
     * @param string $sid
     * @param string $referer
     * @param bool $init
     * @return bool
     */
    private static function initTimes(string $sid, string $referer, bool $init = true): bool
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
        // {"code":0,"message":"0","ttl":1,"data":{"times":2}}
        // {"code":0,"message":"0","ttl":1,"data":{"times":3}}
        if ($init) {
            if ($de_raw['code'] == 0) {
                Log::notice("剩余抽奖次数 {$de_raw['data']['times']}");
                return true;
            }
            Log::warning("获取抽奖次数失败 $raw");
            return false;
        }
        if ($de_raw['code'] == 0) {
            Log::notice("剩余抽奖次数 {$de_raw['data']['times']}");
            if ($de_raw['data']['times'] <= 0) {
                return false;
            }
            return true;
        }
        Log::warning("获取抽奖次数失败 $raw");
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
        // $action_type  4 关注  3 分享
        $payload = [
            'sid' => $sid,
            'action_type' => $action_type,
            'csrf' => getCsrf()
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        // {"code":75405,"message":"抽奖机会用尽啦","ttl":1}
        // {"code":75003,"message":"活动已结束","ttl":1}
        // {"code":0,"message":"0","ttl":1}
        $de_raw = json_decode($raw, true);
        Log::notice("增加抽奖机会#$action_type $raw");

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
        $payload = [
            'sid' => $sid,
            'type' => 1,
            'csrf' => getCsrf()
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        Log::notice("开始抽奖#$num $raw");
        // {"code":0,"message":"0","ttl":1,"data":[{"id":0,"mid":4133274,"num":1,"gift_id":1152,"gift_name":"硬币x6","gift_type":0,"img_url":"https://i0.hdslb.com/bfs/activity-plat/static/b6e956937ee4aefd1e19c01283145fc0/JQ9Y9-KCm_w96_h102.png","type":5,"ctime":1596255796,"cid":0}]}
        // {"code":0,"message":"0","ttl":1,"data":[{"id":0,"mid":4133274,"ip":0,"num":1,"gift_id":0,"gift_name":"未中奖0","gift_type":0,"img_url":"","type":1,"ctime":1616825625,"cid":0,"extra":{}}]}
        if ($de_raw['code'] == 0) {
            $result = "活动->$referer 获得->{$de_raw['data'][0]['gift_name']}";
            Notice::push('activity_lottery', $result);
            return true;
        }
        return false;
    }


}