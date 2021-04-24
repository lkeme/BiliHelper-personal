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
use BiliHelper\Util\XliveHeartBeat;

class CapsuleLottery
{
    use TimeLock;
    use AllotTasks;
    use XliveHeartBeat;

    private static $repository = APP_DATA_PATH . 'capsule_infos.json';
    private static $interval = 60;


    /**
     * @throws \JsonDecodeStream\Exception\TokenizerException
     * @throws \JsonDecodeStream\Exception\SelectorException
     * @throws \JsonDecodeStream\Exception\ParserException
     * @throws \JsonDecodeStream\Exception\CollectorException
     */
    public static function run()
    {
        if (self::getLock() > time() || getenv('USE_CAPSULE') == 'false') {
            return;
        }
        self::allotTasks();
        if (self::workTask()) {
            self::setLock(self::$interval);
        } else {
            self::setLock(self::timing(5) + mt_rand(1, 180));
        }
    }


    /**
     * @use 分配任务
     * @return bool
     * @throws \JsonDecodeStream\Exception\CollectorException
     * @throws \JsonDecodeStream\Exception\ParserException
     * @throws \JsonDecodeStream\Exception\SelectorException
     * @throws \JsonDecodeStream\Exception\TokenizerException
     */
    private static function allotTasks(): bool
    {
        if (self::$work_status['work_updated'] == date("Y/m/d")) {
            return false;
        }
        $parser = self::loadJsonData();
        foreach ($parser->items('data[]') as $act) {
            // 活动无效
            if (is_null($act->coin_id)) {
                continue;
            }
            // 活动实效过期
            if (strtotime($act->expire_at) < time()) {
                continue;
            }
            if ($act->room_id == 0) {
                $room_ids = Live::getAreaRoomList($act->parent_area_id, $act->area_id);
                $act->room_id = array_shift($room_ids);
            }
            // 观看时间
            self::pushTask('watch', $act, true);
            // 抽奖次数
            $arr = range(1, $act->draw_times);
            foreach ($arr as $_) {
                self::pushTask('draw', $act);
            }
        }
        self::$work_status['work_updated'] = date("Y/m/d");
        Log::info('扭蛋抽奖任务分配完成 ' . count(self::$tasks) . ' 个任务待执行');
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
        if ($task['time'] && is_null(self::$work_status['estimated_time'])) {
            self::$work_status['estimated_time'] = time() + $task['act']->watch_time;
        }
        Log::info("执行 {$task['act']->title} #{$task['operation']} 任务");
        // 执行任务
        switch ($task['operation']) {
            case 'watch':
                $interval = self::xliveHeartBeatTask($task['act']->room_id,999,999);
                self::$interval = $interval == 0 ? 60 : $interval;
                break;
            case 'draw':
                self::doLottery($task['act']->coin_id, $task['act']->url, 0);
                break;
            default:
                Log::info("当前 {$task['act']->title} #{$task['operation']} 任务不存在哦");
                break;
        }
        return true;
    }

    /**
     * @use 开始抽奖
     * @param int $coin_id
     * @param string $referer
     * @param int $num
     * @return bool
     */
    private static function doLottery(int $coin_id, string $referer, int $num): bool
    {
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/v1/capsule/open_capsule_by_id';
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => $referer
        ];
        $user_info = User::parseCookies();
        $payload = [
            'id' => $coin_id,
            'count' => 1,
            'type' => 1,
            'platform' => 'web',
            '_' => time() * 1000,
            'csrf' => $user_info['token'],
            'csrf_token' => $user_info['token'],
            'visit_id' => ''
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        Log::notice("开始抽奖#{$num} {$raw}");
        // {"code":0,"message":"0","ttl":1,"data":{"status":false,"isEntity":false,"info":{"coin":1},"awards":[{"name":"谢谢参与","num":1,"text":"谢谢参与 X 1","web_url":"https://i0.hdslb.com/bfs/live/b0fccfb3bac2daae35d7e514a8f6d31530b9add2.png","mobile_url":"https://i0.hdslb.com/bfs/live/b0fccfb3bac2daae35d7e514a8f6d31530b9add2.png","usage":{"text":"很遗憾您未能中奖","url":""},"type":32,"expire":"当天","gift_type":"7290bc172e5ab9e151eb141749adb9dd","gift_value":""}],"text":["谢谢参与 X 1"],"isExCode":false}}
        if ($de_raw['code'] == 0) {
            $result = "活动->{$referer} 获得->{$de_raw['data']['text'][0]}";
            Notice::push('capsule_lottery', $result);
            return true;
        }
        return false;

    }

}