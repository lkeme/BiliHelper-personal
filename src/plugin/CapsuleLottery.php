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
use BiliHelper\Tool\Common;
use BiliHelper\Util\AllotTasks;
use BiliHelper\Util\TimeLock;
use BiliHelper\Util\XliveHeartBeat;

class CapsuleLottery
{
    use TimeLock;
    use AllotTasks;
    use XliveHeartBeat;

    private static string $repository = APP_DATA_PATH . 'capsule_infos.json';
    private static int $interval = 60;


    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('live_capsule')) {
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
     */
    private static function allotTasks(): bool
    {
        if (self::$work_status['work_updated'] == date("Y/m/d")) {
            return false;
        }
        $parser = self::loadJsonData();
        // Converting to array
        foreach ($parser->get('data', []) as $act) {
            // todo 优化处理
            $act = json_decode(json_encode($act));
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
            foreach ($arr as $ignored) {
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
            // Todo 观看 分享 签到任务
            case 'watch':
                // 处理值为空
                if (!is_null($task['act']->room_id)) {
                    $interval = self::xliveHeartBeatTask($task['act']->room_id, 999, 999);
                    self::$interval = ($interval == 0 ? 60 : $interval);
                }
                break;
            case 'draw':
                // 抽奖次数 > 0 开始抽奖
                if (self::getLuckyNum($task['act']->coin_id, $task['act']->url)) {
                    self::doLottery($task['act']->coin_id, $task['act']->url, 0);
                }
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
        $payload = [
            'id' => $coin_id,
            'count' => 1,
            'type' => 1,
            'platform' => 'web',
            '_' => time() * 1000,
            'csrf' => getCsrf(),
            'csrf_token' => getCsrf(),
            'visit_id' => ''
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        Log::notice("开始抽奖#$num $raw");
        // {"code":0,"message":"0","ttl":1,"data":{"status":false,"isEntity":false,"info":{"coin":1},"awards":[{"name":"谢谢参与","num":1,"text":"谢谢参与 X 1","web_url":"https://i0.hdslb.com/bfs/live/b0fccfb3bac2daae35d7e514a8f6d31530b9add2.png","mobile_url":"https://i0.hdslb.com/bfs/live/b0fccfb3bac2daae35d7e514a8f6d31530b9add2.png","usage":{"text":"很遗憾您未能中奖","url":""},"type":32,"expire":"当天","gift_type":"7290bc172e5ab9e151eb141749adb9dd","gift_value":""}],"text":["谢谢参与 X 1"],"isExCode":false}}
        if ($de_raw['code'] == 0) {
            $result = "活动->$referer 获得->{$de_raw['data']['text'][0]}";
            Notice::push('capsule_lottery', $result);
            return true;
        }
        return false;

    }

    /**
     * @use 分享任务
     * @param int $act_id
     * @param string $referer
     */
    private static function taskShare(int $act_id, string $referer): void
    {
        $url = 'https://api.live.bilibili.com/xlive/activity-interface/v1/task/UserShare';
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => 'https://live.bilibili.com/'
        ];
        $payload = [
            'act_id' => $act_id,
            'share_type' => 1,
            'csrf_token' => getCsrf(),
            'csrf' => getCsrf(),
            'visit_id' => '',
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        // {"code":0,"message":"0","ttl":1,"data":{"status":1}}
        $de_raw = json_decode($raw, true);
    }

    /**
     * @use 获取扭蛋池子信息
     * @param int $pool_id
     * @param string $referer
     */
    private static function getPoolDetail(int $pool_id, string $referer): void
    {
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/v1/capsule/get_pool_detail';
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => $referer
        ];
        $payload = [
            'pool_id' => $pool_id,
            '_' => Common::getUnixTimestamp()
        ];
        $raw = Curl::get('pc', $url, $payload);
        // {"code":0,"message":"0","ttl":1,"data":{"id":145,"coin":3,"coin_id":131,"title":"OWL2021","open_num_1":1,"open_num_2":10,"open_num_3":100,"status":0,"gift_list":[{"id":1237,"name":"谢谢参与","num":1,"web_url":"https://i0.hdslb.com/bfs/live/b0fccfb3bac2daae35d7e514a8f6d31530b9add2.png","mobile_url":"https://i0.hdslb.com/bfs/live/b0fccfb3bac2daae35d7e514a8f6d31530b9add2.png","usage":{"text":"谢谢参与","url":""},"type":32,"expire":"当天","gift_type":"7290bc172e5ab9e151eb141749adb9dd"},{"id":1238,"name":"杭州闪电队主场队服","num":1,"web_url":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_url":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","usage":{"text":"新款杭州闪电队队服","url":""},"type":100024,"expire":"当天","gift_type":"895ff9e63081033072a1bc5694d10095"},{"id":1239,"name":"上海龙之队主场队服","num":1,"web_url":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_url":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","usage":{"text":"新款上海龙之队队服","url":""},"type":100024,"expire":"当天","gift_type":"a314efd84276f0be571492823b1c0edb"},{"id":1240,"name":"广州冲锋队主场队服","num":1,"web_url":"https://i0.hdslb.com/bfs/live/cdc33523a1b018ee6af447f528c56848f5556eb8.png","mobile_url":"https://i0.hdslb.com/bfs/live/cdc33523a1b018ee6af447f528c56848f5556eb8.png","usage":{"text":"新款广州冲锋队队服","url":""},"type":100024,"expire":"当天","gift_type":"6d46360bc93b8b07b4c64efb9350579a"},{"id":1241,"name":"谢谢参与","num":1,"web_url":"https://i0.hdslb.com/bfs/live/b0fccfb3bac2daae35d7e514a8f6d31530b9add2.png","mobile_url":"https://i0.hdslb.com/bfs/live/b0fccfb3bac2daae35d7e514a8f6d31530b9add2.png","usage":{"text":"谢谢参与","url":""},"type":32,"expire":"当天","gift_type":"7290bc172e5ab9e151eb141749adb9dd"},{"id":1242,"name":"成都猎人队主场队服","num":1,"web_url":"https://i0.hdslb.com/bfs/live/625c55aa7a020040466cc7a842ea0c57ac4f484c.png","mobile_url":"https://i0.hdslb.com/bfs/live/625c55aa7a020040466cc7a842ea0c57ac4f484c.png","usage":{"text":"新款成都猎人队队服","url":""},"type":100024,"expire":"当天","gift_type":"51ecabc603848979410202a93391cd35"},{"id":1243,"name":"随机战队客场队服","num":1,"web_url":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","mobile_url":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","usage":{"text":"随机一件OWL战队客场队服","url":""},"type":100024,"expire":"当天","gift_type":"b2ec92156e57708caf923b1c18309fea"},{"id":1244,"name":"哔哩哔哩月度大会员","num":1,"web_url":"https://i0.hdslb.com/bfs/live/49b6d12eadc81acf68d5378b257df78ec38b4355.png","mobile_url":"https://i0.hdslb.com/bfs/live/49b6d12eadc81acf68d5378b257df78ec38b4355.png","usage":{"text":"bilibili月度大会员","url":""},"type":100024,"expire":"当天","gift_type":"78db25f308b8b8db86ba1a172c2deb35"}],"is_login":true,"list":[{"num":1,"gift":"哔哩哔哩月度大会员","date":"2021-05-10","name":"初夏的戏匣子","web_image":"https://i0.hdslb.com/bfs/live/49b6d12eadc81acf68d5378b257df78ec38b4355.png","mobile_image":"https://i0.hdslb.com/bfs/live/49b6d12eadc81acf68d5378b257df78ec38b4355.png","count":1},{"num":1,"gift":"杭州闪电队主场队服","date":"2021-05-10","name":"hentaiDIAO","web_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","count":1},{"num":1,"gift":"随机战队客场队服","date":"2021-05-10","name":"哗哗はな","web_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","mobile_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","count":10},{"num":1,"gift":"杭州闪电队主场队服","date":"2021-05-10","name":"Promissio小约定","web_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","count":1},{"num":1,"gift":"上海龙之队主场队服","date":"2021-05-10","name":"小新笔记","web_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","count":10},{"num":1,"gift":"广州冲锋队主场队服","date":"2021-05-10","name":"晚来天欲雪yyy","web_image":"https://i0.hdslb.com/bfs/live/cdc33523a1b018ee6af447f528c56848f5556eb8.png","mobile_image":"https://i0.hdslb.com/bfs/live/cdc33523a1b018ee6af447f528c56848f5556eb8.png","count":1},{"num":1,"gift":"成都猎人队主场队服","date":"2021-05-10","name":"Histo2y","web_image":"https://i0.hdslb.com/bfs/live/625c55aa7a020040466cc7a842ea0c57ac4f484c.png","mobile_image":"https://i0.hdslb.com/bfs/live/625c55aa7a020040466cc7a842ea0c57ac4f484c.png","count":1},{"num":1,"gift":"随机战队客场队服","date":"2021-05-10","name":"蜀黍我最怕亏钱啦","web_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","mobile_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","count":1},{"num":1,"gift":"上海龙之队主场队服","date":"2021-05-10","name":"国王大道修车师傅","web_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","count":10},{"num":1,"gift":"杭州闪电队主场队服","date":"2021-05-10","name":"丁马的早晨","web_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","count":10},{"num":1,"gift":"随机战队客场队服","date":"2021-05-10","name":"丁马的早晨","web_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","mobile_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","count":10},{"num":1,"gift":"上海龙之队主场队服","date":"2021-05-10","name":"-St-John","web_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","count":10},{"num":1,"gift":"哔哩哔哩月度大会员","date":"2021-05-03","name":"那个臭打游戏的","web_image":"https://i0.hdslb.com/bfs/live/49b6d12eadc81acf68d5378b257df78ec38b4355.png","mobile_image":"https://i0.hdslb.com/bfs/live/49b6d12eadc81acf68d5378b257df78ec38b4355.png","count":1},{"num":1,"gift":"成都猎人队主场队服","date":"2021-05-03","name":"金小伍","web_image":"https://i0.hdslb.com/bfs/live/625c55aa7a020040466cc7a842ea0c57ac4f484c.png","mobile_image":"https://i0.hdslb.com/bfs/live/625c55aa7a020040466cc7a842ea0c57ac4f484c.png","count":1},{"num":1,"gift":"广州冲锋队主场队服","date":"2021-05-03","name":"不灵梦Blame","web_image":"https://i0.hdslb.com/bfs/live/cdc33523a1b018ee6af447f528c56848f5556eb8.png","mobile_image":"https://i0.hdslb.com/bfs/live/cdc33523a1b018ee6af447f528c56848f5556eb8.png","count":1},{"num":1,"gift":"随机战队客场队服","date":"2021-05-03","name":"幻化叶之韵","web_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","mobile_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","count":10},{"num":1,"gift":"上海龙之队主场队服","date":"2021-05-03","name":"-莉娅菠萝-","web_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","count":10},{"num":1,"gift":"杭州闪电队主场队服","date":"2021-05-03","name":"康斯坦丁Constantine.","web_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","count":1},{"num":1,"gift":"随机战队客场队服","date":"2021-05-03","name":"夜魅三清","web_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","mobile_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","count":1},{"num":1,"gift":"上海龙之队主场队服","date":"2021-05-03","name":"夜魅三清","web_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","count":1},{"num":1,"gift":"随机战队客场队服","date":"2021-05-03","name":"蓝岚染","web_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","mobile_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","count":10},{"num":1,"gift":"杭州闪电队主场队服","date":"2021-05-03","name":"用了五级经验卡","web_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","count":1},{"num":1,"gift":"上海龙之队主场队服","date":"2021-05-03","name":"蜀黍我最怕亏钱啦","web_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","count":10},{"num":1,"gift":"杭州闪电队主场队服","date":"2021-05-03","name":"aii01992","web_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","count":10},{"num":1,"gift":"哔哩哔哩月度大会员","date":"2021-04-26","name":"妄想逃离现实的星轨君","web_image":"https://i0.hdslb.com/bfs/live/49b6d12eadc81acf68d5378b257df78ec38b4355.png","mobile_image":"https://i0.hdslb.com/bfs/live/49b6d12eadc81acf68d5378b257df78ec38b4355.png","count":1},{"num":1,"gift":"杭州闪电队主场队服","date":"2021-04-26","name":"乌鲁乌鲁崽","web_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","count":1},{"num":1,"gift":"上海龙之队主场队服","date":"2021-04-26","name":"思维回廊","web_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","count":1},{"num":1,"gift":"随机战队客场队服","date":"2021-04-26","name":"思维回廊","web_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","mobile_image":"https://i0.hdslb.com/bfs/live/2198f9659645d28a7fc20c15e93f050a30706016.png","count":1},{"num":1,"gift":"上海龙之队主场队服","date":"2021-04-26","name":"LilPank","web_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","mobile_image":"https://i0.hdslb.com/bfs/live/1e53fc5f1f13c1e2e30eaa7504bc0d939e77df42.png","count":10},{"num":1,"gift":"杭州闪电队主场队服","date":"2021-04-26","name":"一日学滴滴","web_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","mobile_image":"https://i0.hdslb.com/bfs/live/e7cc2043c85c43f8bdd338a693eef3fe3fc18757.png","count":10}]}}
        $de_raw = json_decode($raw, true);
    }

    /**
     * @use 获取扭蛋信息
     * @param int $coin_id
     * @param string $referer
     * @return array
     */
    private static function getCapsuleInfo(int $coin_id, string $referer): array
    {
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/v1/capsule/get_capsule_info_v3';
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => $referer
        ];
        $payload = [
            'id' => $coin_id,
            'from' => 'web',
            '-' => Common::getUnixTimestamp()
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        // data -> status 0||2
        // {"code":0,"message":"0","ttl":1,"data":{"coin":9,"rule":"2020年英雄联盟职业联赛春季赛抽奖奖池","gift_list":[{"name":"辣条","num":1,"web_url":"https://i0.hdslb.com/bfs/live/48605b0fe9eca5aba87f93da0fa0aa361c419835.png","mobile_url":"https://i0.hdslb.com/bfs/live/8e7a4dc8de374faee22fca7f9a3f801a1712a36b.png","usage":{"text":"辣条是一种直播虚拟礼物，可以在直播间送给自己喜爱的主播哦~","url":""},"type":1,"expire":"3天","gift_type":"325a347f91903c0353385e343dd358f0"},{"name":"3天头衔续期卡","num":1,"web_url":"https://i0.hdslb.com/bfs/live/48aecec2d7243b6f8bd17f20ff715db89f9adcec.png","mobile_url":"https://i0.hdslb.com/bfs/live/48aecec2d7243b6f8bd17f20ff715db89f9adcec.png","usage":{"text":"3天头衔续期卡*1","url":""},"type":21,"expire":"1周","gift_type":"4bda2f960342d86a426ebc067d3633ed"},{"name":"LPL2020助威","num":1,"web_url":"https://i0.hdslb.com/bfs/live/d9ee9558fcc438c99deb00ed1f6bd3707bac3452.png","mobile_url":"https://i0.hdslb.com/bfs/live/d9ee9558fcc438c99deb00ed1f6bd3707bac3452.png","usage":{"text":"2020LPL限定头衔","url":""},"type":2,"expire":"1周","gift_type":"b114c47920fce2aca5fca7a27cca5915"},{"name":"随机英雄联盟角色手办","num":1,"web_url":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_url":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","usage":{"text":"随机英雄联盟角色手办*1","url":""},"type":100024,"expire":"当天","gift_type":"6a4ae5853753d67d07cea2b1750795f4"},{"name":"2020LPL彩色弹幕","num":1,"web_url":"https://i0.hdslb.com/bfs/live/9a571f9d82c2a8cbbe869fd92796e70b19f9c2cc.png","mobile_url":"https://i0.hdslb.com/bfs/live/9a571f9d82c2a8cbbe869fd92796e70b19f9c2cc.png","usage":{"text":"LPL专属彩色弹幕","url":""},"type":20,"expire":"3天","gift_type":"14e40c6949800b5d840011e47e54d0c5"},{"name":"7天头衔续期卡","num":1,"web_url":"https://i0.hdslb.com/bfs/live/a2ffb62dc90d4896ddc3d1dcdbe83ac5d1dd7328.png","mobile_url":"https://i0.hdslb.com/bfs/live/a2ffb62dc90d4896ddc3d1dcdbe83ac5d1dd7328.png","usage":{"text":"7天头衔续期卡*1","url":""},"type":21,"expire":"1周","gift_type":"bbfc114b65126486a40c81daedd911e5"},{"name":"2020LPL春季赛助威券","num":1,"web_url":"https://i0.hdslb.com/bfs/live/be4cdecc4809caf8aa21817880a3283672b5a477.png","mobile_url":"https://i0.hdslb.com/bfs/live/be4cdecc4809caf8aa21817880a3283672b5a477.png","usage":{"text":"再来一次！(✪ω✪)","url":""},"type":22,"expire":"当天","gift_type":"96d2b8187ec6564fa40733153a41ac14"},{"name":"30天头衔续期卡","num":1,"web_url":"https://i0.hdslb.com/bfs/live/fc49e08115db6edd0276fba69ed8835a64714441.png","mobile_url":"https://i0.hdslb.com/bfs/live/fc49e08115db6edd0276fba69ed8835a64714441.png","usage":{"text":"30天头衔续期卡*1","url":""},"type":21,"expire":"1周","gift_type":"02810fd04244c47952bd4ed0b35617db"},{"name":"辣条","num":233,"web_url":"https://i0.hdslb.com/bfs/live/48605b0fe9eca5aba87f93da0fa0aa361c419835.png","mobile_url":"https://i0.hdslb.com/bfs/live/8e7a4dc8de374faee22fca7f9a3f801a1712a36b.png","usage":{"text":"辣条是一种直播虚拟礼物，可以在直播间送给自己喜爱的主播哦~","url":""},"type":1,"expire":"3天","gift_type":"a6d260760dfb1fe9f5375b3c8c7bd7ad"},{"name":"随机提伯斯熊毛绒公仔","num":1,"web_url":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_url":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","usage":{"text":"提伯斯熊毛绒公仔*1","url":""},"type":100024,"expire":"当天","gift_type":"2df71ff3306a4a2b4a627889cbd63c5b"}],"change_num":10000,"status":0,"is_login":true,"user_score":90000,"list":[{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-04-23","name":"nXBo7p0svjm","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-04-20","name":"z98rwt","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-04-18","name":"wBQW6Z6jgbb","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-04-13","name":"dU9449p1zkz","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-04-10","name":"ckcs8151","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-04-06","name":"l1d9fgn1gl","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-03-31","name":"rlBF7ivbffe","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-03-29","name":"卟要悔","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-03-23","name":"就这样8丶","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-03-17","name":"bIud77Vsory","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1}]}}
        return json_decode($raw, true);
    }

    /**
     * @获取剩余抽奖次数
     * @param int $coin_id
     * @param string $referer
     * @return int
     */
    private static function getLuckyNum(int $coin_id, string $referer): int
    {
        $capsule_info = self::getCapsuleInfo($coin_id, $referer);
        if ($capsule_info['code'] == 0) {
            Log::info("获取剩余抽奖次数成功 {$capsule_info['data']['coin']}");
            return $capsule_info['data']['coin'];
        }
        Log::warning("获取剩余抽奖次数失败 " . json_encode($capsule_info, true));
        return 0;
    }


    /**
     * @use 获取用户活动任务
     * @param int $act_id
     * @param string $referer
     */
    private static function userActTask(int $act_id, string $referer): void
    {
        $url = 'https://api.live.bilibili.com/xlive/activity-interface/v1/activitytask/user_acttask/info';
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => $referer
        ];
        $payload = [
            'act_id' => $act_id,
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        // {"code":0,"message":"0","ttl":1,"data":{"act_info":{"id":30008,"title":"OWL2021","desc":"","start_time":0,"end_time":0,"sys_time":1621059078,"act_status":0},"task_list":[{"task_id":19,"task_name":"分享有礼","task_desc":"每日首次分享赛事直播间","task_cycle_type":0,"task_cycle_id":20210515,"progress":{"list":[{"progress_list":[{"task_id":19,"task_type":4,"task_level":1,"target_num":1,"current_num":0,"progress_status":0,"task_status":0,"real_num":0}],"reward_list":["OWL2021补给券"],"task_status":0,"draw_status":0,"task_title":"分享有礼","task_desc":"每日首次分享赛事直播间"}],"task_map":{"4":{"task_id":19,"task_type":4,"task_level":1,"target_num":1,"current_num":0,"progress_status":0,"task_status":0,"real_num":0}},"level":0,"is_finish":0}},{"task_id":18,"task_name":"关注有礼","task_desc":"每日首次关注任意3位推荐主播","task_cycle_type":0,"task_cycle_id":20210515,"progress":{"list":[{"progress_list":[{"task_id":18,"task_type":5,"task_level":1,"target_num":3,"current_num":0,"progress_status":0,"task_status":0,"real_num":0}],"reward_list":["OWL2021补给券"],"task_status":0,"draw_status":0,"task_title":"关注有礼","task_desc":"每日首次关注任意3位推荐主播"}],"task_map":{"5":{"task_id":18,"task_type":5,"task_level":1,"target_num":3,"current_num":0,"progress_status":0,"task_status":0,"real_num":0}},"level":0,"is_finish":0}}]}}
        $de_raw = json_decode($raw, true);
    }

    /**
     * @use 领取任务奖励
     * @param int $act_id
     * @param int $task_id
     * @param int $level_id
     * @param int $cycle_id
     * @param string $referer
     */
    private static function getTaskAward(int $act_id, int $task_id, int $level_id, int $cycle_id, string $referer): void
    {
        $url = 'https://api.live.bilibili.com/xlive/activity-interface/v1/activitytask/user_acttask/getaward';
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => $referer
        ];
        $payload = [
            'act_id' => $act_id,
            'task_id' => $task_id,
            'level_id' => 1,
            'cycle_id' => $cycle_id,
            'csrf' => getCsrf(),
            'csrf_token' => getCsrf(),
            'visit_id' => ''
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        // {"code":0,"message":"0","ttl":1,"data":{}}
        $de_raw = json_decode($raw, true);

    }
}