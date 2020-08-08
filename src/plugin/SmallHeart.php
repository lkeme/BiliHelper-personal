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
use BiliHelper\Tool\Generator;

class SmallHeart
{
    use TimeLock;

    private static $enc_server = null; // 加密服务器 配置文件

    private static $hb_payload = []; // 心跳请求数据
    private static $hb_headers = []; // 心跳请求头

    private static $hb_count = 0; // 心跳次数 max 24
    private static $hb_room_info = []; // 心跳带勋章房间信息

    private static $fans_medals = []; // 全部勋章
    private static $grey_fans_medals = []; // 灰色勋章

    private static $metal_lock = 0; // 勋章时间锁

    public static function run()
    {
        if (!self::init()) {
            return;
        }

        if (self::$metal_lock < time()) {
            self::polishMetal();
            self::$metal_lock = time() + 8 * 60 * 60;
        }
        if (self::getLock() < time()) {
            self::heartBeat();
            if (self::$hb_count >= 30) {
                self::resetVar();
                self::setLock(self::timing(2));
            } else {
                self::setLock(5 * 60);
            }
        }
    }

    /**
     * @use 重置变量
     */
    private static function resetVar()
    {
        self::$hb_payload = []; // 心跳请求数据
        self::$hb_headers = []; // 心跳请求头

        self::$hb_count = 0; // 心跳次数 max 24
        self::$hb_room_info = []; // 心跳带勋章房间信息
    }

    /**
     * @use init
     * @return bool
     */
    private static function init(): bool
    {
        if (getenv('USE_HEARTBEAT') == 'false' || getenv('ENC_SERVER') == '') {
            return false;
        }
        if (is_null(self::$enc_server)) {
            self::$enc_server = getenv('ENC_SERVER');
        }
        return true;
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
        if (empty(self::$hb_room_info)) {
            $metal = self::$fans_medals[array_rand(self::$fans_medals)];
            $room_info = Live::webGetRoomInfo($metal['roomid']);
        }
        if (self::$hb_count == 0) {
            $e_data = self::eHeartBeat($room_info['data']['room_info']);
            if (!$e_data['status']) {
                // 错误级别
                return;
            }
            self::$hb_count += 1;
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
        Log::info("小心心礼物X-{$index}心跳成功");
        return ['status' => true];
    }


    /**
     * @use 加密参数S
     * @param int $index
     * @return array
     */
    private static function encParamS(int $index): array
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
        Log::info("S参数加密 {$de_raw['s']}");

        return [
            's' => $de_raw['s'],
            'payload' => $payload['t']
        ];
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