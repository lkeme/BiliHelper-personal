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
use BiliHelper\Tool\Generator;

class CapsuleLottery
{
    use TimeLock;
    use AllotTasks;

    private static $repository = APP_DATA_PATH . 'capsule_infos.json';

    private static $work_room_id = null;
    private static $enc_server = null; // 加密服务器 配置文件
    private static $hb_payload = []; // 心跳请求数据
    private static $hb_headers = []; // 心跳请求头
    private static $hb_count_total = 0;
    private static $hb_count = 0; // 心跳次数 max 24
    private static $hb_room_info = []; // 心跳带勋章房间信息
    private static $heartbeat_interval = 60; // 每次跳动时间


    public static function run()
    {
        if (self::getLock() > time() || !self::init()) {
            return;
        }
        self::allotTasks();
        if (self::workTask()) {
            self::setLock(self::$heartbeat_interval);
        } else {
            self::setLock(self::timing(5) + mt_rand(1, 180));
        }
    }

    /**
     * @use init
     * @return bool
     */
    private static function init(): bool
    {
        if (getenv('USE_CAPSULE') == 'false' || getenv('ENC_SERVER') == '') {
            return false;
        }
        if (is_null(self::$enc_server)) {
            self::$enc_server = getenv('ENC_SERVER');
        }
        return true;
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
    private static function workTask()
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
                self::heartBeat($task['act']->room_id);
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
     * @use 重置变量
     * @param false $reset_num
     */
    private static function resetVar($reset_num = false)
    {
        self::$hb_payload = []; // 心跳请求数据
        self::$hb_headers = []; // 心跳请求头
        if ($reset_num) {
            self::$hb_count_total = 0;
        }
        self::$hb_room_info = []; // 心跳带勋章房间信息
        self::$hb_count = 0; // 心跳次数 max 24
        self::$heartbeat_interval = 60; // 跳变时间
    }


    /**
     * @use 心跳处理
     * @param int $room_id
     */
    private static function heartBeat(int $room_id)
    {
        if (self::$work_room_id != $room_id) {
            self::resetVar();
            self::$work_room_id = $room_id;
        }
        if (empty(self::$hb_room_info)) {
            self::$hb_room_info = Live::webGetRoomInfo($room_id);
        }
        if (!self::$hb_count) {
            $e_data = self::eHeartBeat(self::$hb_room_info['data']['room_info']);
            if (!$e_data['status']) {
                // 错误级别
                return;
            }
            self::$hb_count += 1;
            self::$hb_count_total += 1;
            self::$hb_payload = $e_data['payload'];
            self::$hb_headers = $e_data['headers'];
            return;
        }
        $x_data = self::xHeartBeat(self::$hb_count);
        if (!$x_data['status']) {
            // 错误级别
            self::resetVar();
            return;
        }
        self::$hb_count_total += 1;
        self::$hb_count += 1;
    }

    /**
     * @use E心跳
     * @param array $room_info
     * @param int $index
     * @return array|bool[]
     */
    private static function eHeartBeat(array $room_info, $index = 0): array
    {
        $url = 'https://live-trace.bilibili.com/xlive/data-interface/v1/x25Kn/E';
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin' => 'https://live.bilibili.com',
            'Referer' => 'https://live.bilibili.com/' . $room_info['room_id'],
        ];
        $user_info = User::parseCookies();
        $payload = [
            'id' => json_encode([$room_info['parent_area_id'], $room_info['area_id'], $index, $room_info['room_id']], true),
            'device' => json_encode([
                Generator::hash(), Generator::uuid4()
            ], true),
            'ts' => time() * 1000,
            'is_patch' => 0,
            'heart_beat' => [],
            'ua' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:78.0) Gecko/20100101 Firefox/78.0',
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => ''
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1,"data":{"timestamp":1595342828,"heartbeat_interval":300,"secret_key":"seacasdgyijfhofiuxoannn","secret_rule":[2,5,1,4],"patch_status":2}}
        if ($de_raw['code'] != 0) {
            Log::warning("小心心礼物E-{$index}心跳失败");
            return ['status' => false];
        }
        Log::info("小心心礼物E-{$index}心跳成功");
        // Log::info($raw);
        $payload['ets'] = $de_raw['data']['timestamp'];
        $payload['secret_key'] = $de_raw['data']['secret_key'];
        $payload['heartbeat_interval'] = $de_raw['data']['heartbeat_interval'];
        $payload['secret_rule'] = $de_raw['data']['secret_rule'];
        // 自动跳变时间
        self::$heartbeat_interval = $de_raw['data']['heartbeat_interval'];
        return [
            'status' => true,
            'payload' => $payload,
            'headers' => $headers,
        ];
    }

    /**
     * @use X心跳
     * @param int $index
     * @return array|bool[]
     */
    private static function xHeartBeat(int $index = 1): array
    {
        $s_data = self::encParamS($index);
        if (!$s_data) {
            return ['status' => false];
        }
        $s = $s_data['s'];
        $t = $s_data['payload'];

        $url = 'https://live-trace.bilibili.com/xlive/data-interface/v1/x25Kn/X';
        $user_info = User::parseCookies();
        $payload = [
            's' => $s,
            'id' => $t['id'],
            'device' => $t['device'],
            'ets' => $t['ets'],
            'benchmark' => $t['benchmark'],
            'time' => $t['time'],
            'ts' => $t['ts'],
            'ua' => $t['ua'],
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => ''
        ];
        // print_r($payload);
        $raw = Curl::post('pc', $url, $payload, self::$hb_headers);
        $de_raw = json_decode($raw, true);
        # {"code":0,"message":"0","ttl":1,"data":{"heartbeat_interval":300,"timestamp":1595346846,"secret_rule":[2,5,1,4],"secret_key":"seacasdgyijfhofiuxoannn"}}
        if ($de_raw['code'] != 0) {
            Log::warning("小心心礼物X-{$index}心跳失败");
            return ['status' => false];
        }
        self::$hb_payload['ets'] = $de_raw['data']['timestamp'];
        self::$hb_payload['secret_key'] = $de_raw['data']['secret_key'];
        self::$hb_payload['heartbeat_interval'] = $de_raw['data']['heartbeat_interval'];
        // 自动跳变时间
        self::$heartbeat_interval = $de_raw['data']['heartbeat_interval'];
        Log::info("小心心礼物X-{$index}心跳成功");
        return ['status' => true];
    }

    /**
     * @use 加密参数S
     * @param int $index
     * @return array|false
     */
    private static function encParamS(int $index)
    {
        // 转换index
        $temp = json_decode(self::$hb_payload['id'], true);
        $temp[2] += 1;
        self::$hb_payload['id'] = json_encode($temp, true);
        // 加密部分
        $payload = [
            't' => [
                'id' => self::$hb_payload['id'],
                'device' => self::$hb_payload['device'],
                'ets' => self::$hb_payload['ets'],
                'benchmark' => self::$hb_payload['secret_key'],
                'time' => self::$hb_payload['heartbeat_interval'],
                'ts' => time() * 1000,
                'ua' => self::$hb_payload['ua']
            ],
            'r' => self::$hb_payload['secret_rule']
        ];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $data = Curl::put('other', self::$enc_server, $payload, $headers);
        $de_raw = json_decode($data, true);
        if ($de_raw['code'] == 0) {
            Log::info("S加密成功 {$de_raw['s']}");
            return [
                's' => $de_raw['s'],
                'payload' => $payload['t']
            ];
        } else {
            Log::warning("S加密成功 {$de_raw['message']}");
            return false;
        }
    }

    /**
     * @use 开始抽奖
     * @param int $coin_id
     * @param string $referer
     * @param int $num
     * @return bool
     */
    private static function doLottery(int $coin_id, string $referer, int $num)
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