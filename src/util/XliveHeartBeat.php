<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Util;

use BiliHelper\Core\Curl;
use BiliHelper\Core\Log;
use BiliHelper\Plugin\Live;
use BiliHelper\Tool\Generator;
use JetBrains\PhpStorm\ArrayShape;

trait XliveHeartBeat
{

    protected static array|null $_data = ['id' => []]; // data [ets, benchmark, time, secret_rule, id]  data->id [parent_area_id, area_id, 0, room_id]
    protected static array $_secret_rule = []; // secret_rule [2, 3, 1, 5]
    protected static array $_room_info = []; // 心跳房间信息

    protected static int $_retry = 3; // 重试次数
    protected static int $_count_num = 0; // 计数
    protected static int $_count_time = 0; // 计时间

    protected static int $_current_room_id = 0; // 当前运行的ROOM_ID
    protected static string|null $_enc_server = null; // 加密服务器 依赖配置文件

    protected static int $_default = 0; // 默认值

    // 请求配置
    protected static string $_user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.85 Safari/537.36';
    protected static array $_headers = [
        'content-type' => 'application/x-www-form-urlencoded',
        'origin' => 'https://live.bilibili.com',
        'referer' => 'https://live.bilibili.com/',
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.85 Safari/537.36'
    ];

    /**
     * @use 任务接口
     * @param int $room_id
     * @param int $max_time
     * @param int $max_num
     * @return mixed
     */
    protected static function xliveHeartBeatTask(int $room_id, int $max_time, int $max_num): mixed
    {
        // 加载依赖
        if (!static::depend()) {
            return static::$_default;
        }
        // 对比当前运行
        if (static::$_current_room_id != $room_id) {
            static::resetVar(true);
            static::$_current_room_id = $room_id;
        }
        // 获取房间信息
        if (empty(static::$_room_info)) {
            $r_data = Live::webGetRoomInfo($room_id);
            if ($r_data['code'] != 0) {
                Log::warning('直播间信息获取失败');
                return static::$_default;
            }
            static::$_room_info = $r_data;
            $rdata = $r_data['data'];
            $parent_area_id = $rdata['room_info']['parent_area_id'];
            $area_id = $rdata['room_info']['area_id'];
            # 短位转长位
            $room_id = $rdata['room_info']['room_id'];
            static::$_data['id'] = [$parent_area_id, $area_id, 0, $room_id];
        }
        // 执行心跳
        $r_data = static::heartBeatIterator();
        $index = static::$_data['id'][2];
        if ($r_data['code'] != 0) {
            Log::warning("心跳失败-$index {$r_data['message']}");
            // 失败心跳
            if (static::$_retry) {
                // 重试次数 > 1 , 不全部清除
                static::resetVar(true);
                static::$_retry -= 1;
            } else {
                // 重试次数 < 1 , 全部清除
                static::resetVar(true);
            }
            return static::$_default;
        } else {
            // 成功心跳
            static::$_count_num += 1;
            static::$_count_time += $r_data['heartbeat_interval'];

            // 最大次数限制
            if ($max_num <= static::$_count_num) {
                // 成功在id为{room_id}的直播间发送完{ii}次心跳，退出直播心跳(达到最大心跳次数)
            }
            // 最大时间限制
            if ($max_time <= static::$_count_time) {
                //成功在id为{room_id}的直播间发送第{ii}次心跳
            }
            $minute = round(static::$_count_time / 60) - 1;
            Log::notice("已在直播间 $room_id 连续观看了 $minute 分钟");
            return $r_data['heartbeat_interval'];
        }
    }

    /**
     * @use 心跳迭代
     * @return array
     */
    protected static function heartBeatIterator(): array
    {
//        print_r(static::$_data);
        $rdata = [];
        # 第1次执行 eHeartBeat
        if (static::$_data['id'][2] == 0) {
            $r_data = static::eHeartBeat(static::$_data['id']);
        } else {
            # 第1次之后执行 xHeartBeat
            static::$_data['ts'] = time() * 1000;
            static::$_data['s'] = static::encParamS(static::$_data, static::$_secret_rule);
            if (!static::$_data['s']) {
                return [
                    'code' => 404,
                    'message' => '心跳加密错误',
                    'heartbeat_interval' => static::$_default
                ];
            }
            $r_data = static::xHeartBeat(static::$_data['id']);
        }
        if ($r_data['code'] == 0) {
            $rdata = $r_data['data'];
            static::$_data['ets'] = $rdata['timestamp'];
            static::$_data['benchmark'] = $rdata['secret_key'];
            static::$_data['time'] = $rdata['heartbeat_interval'];
            static::$_secret_rule = $rdata['secret_rule'];
            static::$_data['id'][2] += 1;
        }
        Log::debug(json_encode(static::$_data['id'], true));
        return [
            'code' => $r_data['code'],
            'message' => $r_data['message'],
            'heartbeat_interval' => array_key_exists('heartbeat_interval', $rdata) ? $rdata['heartbeat_interval'] : static::$_default
        ];
    }

    /**
     * @use E心跳
     * @param array $id
     * @return array
     */
    protected static function eHeartBeat(array $id): array
    {
        $url = 'https://live-trace.bilibili.com/xlive/data-interface/v1/x25Kn/E';
        $payload = [
            'id' => json_encode([$id[0], $id[1], $id[2], $id[3]], true),
            'device' => json_encode([Generator::hash(), Generator::uuid4()], true),
            'ts' => time() * 1000,
            'is_patch' => 0,
            'heart_beat' => [],
            'ua' => static::$_user_agent,
            'csrf_token' => getCsrf(),
            'csrf' => getCsrf(),
            'visit_id' => ''
        ];
        // print_r($payload);
        Log::debug(json_encode($payload, true));
        $raw = Curl::post('pc', $url, $payload, static::$_headers);
        // {'code':0,'message':'0','ttl':1,'data':{'timestamp':1595342828,'heartbeat_interval':300,'secret_key':'seacasdgyijfhofiuxoannn','secret_rule':[2,5,1,4],'patch_status':2}}

        unset($payload['id']);
        static::$_data = array_merge_recursive(static::$_data, $payload);

        return json_decode($raw, true);
    }

    /**
     * @use X心跳
     * @param array $id
     * @return array
     */
    protected static function xHeartBeat(array $id): array
    {
        $url = 'https://live-trace.bilibili.com/xlive/data-interface/v1/x25Kn/X';
        $payload = [
            's' => static::$_data['s'],
            'id' => json_encode([$id[0], $id[1], $id[2], $id[3]], true),
            'device' => static::$_data['device'],
            'ets' => static::$_data['ets'],
            'benchmark' => static::$_data['benchmark'],
            'time' => static::$_data['time'],
            'ts' => static::$_data['ts'],
            'ua' => static::$_data['ua'],
            'csrf_token' => static::$_data['csrf_token'],
            'csrf' => static::$_data['csrf'],
            'visit_id' => ''
        ];
//        print_r($payload);
        Log::debug(json_encode($payload, true));
        $raw = Curl::post('pc', $url, $payload, static::$_headers);
        # {"code":0,"message":"0","ttl":1,"data":{"heartbeat_interval":60,"timestamp":1619419450,"secret_rule":[2,5,1,4],"secret_key":"seacasdgyijfhofiuxoannn"}}
        # {'code':0,'message':'0','ttl':1,'data':{'heartbeat_interval':300,'timestamp':1595346846,'secret_rule':[2,5,1,4],'secret_key':'seacasdgyijfhofiuxoannn'}}
        return json_decode($raw, true);
    }

    /**
     * @use 加密参数S
     * @param array $t
     * @param array $r
     * @return string|false
     */
    protected static function encParamS(array $t, array $r): bool|string
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];
        // 加密部分
        $payload = [
            't' => static::formatT($t),
            'r' => static::formatR($r)
        ];
//        print_r($payload);
        $data = Curl::put('other', static::$_enc_server, $payload, $headers);
        $de_raw = json_decode($data, true);
        if ($de_raw['code'] == 0) {
            if (array_key_exists('s', $de_raw)) {
                // Log::info("S加密成功 {$de_raw['s']}");
                return $de_raw['s'];
            }
            Log::warning("参数S加密失败: 加密服务器暂时错误，请检查更换");
        } else {
            Log::warning("参数S加密失败: {$de_raw['message']}");
        }
        return false;
    }

    /**
     * @use 格式T
     * @param array $t
     * @return array
     */
    #[ArrayShape(['id' => "mixed", 'device' => "mixed", 'ets' => "mixed", 'benchmark' => "mixed", 'time' => "mixed", 'ts' => "mixed", 'ua' => "mixed"])]
    protected static function formatT(array $t): array
    {
//        print_r($t);
        return [
            'id' => $t['id'],
            'device' => $t['device'],
            'ets' => $t['ets'],
            'benchmark' => $t['benchmark'],
            'time' => $t['time'],
            'ts' => $t['ts'],
            'ua' => $t['ua'],
        ];
    }

    /**
     * @use 格式R
     * @param array $r
     * @return array
     */
    protected static function formatR(array $r): array
    {
        return $r;
    }

    /**
     * @use 重置变量
     * @param false $force
     */
    protected static function resetVar(bool $force = false)
    {
        if ($force) {
            static::$_room_info = [];
            static::$_current_room_id = 0;

            static::$_retry = 3;
            static::$_count_num = 0;
            static::$_count_time = 0;
        }
        static::$_data = null;
        static::$_data = ['id' => []];
        $data = [
            'id' => static::$_data['id'],
        ];
        $data["id"][2] = 0;
        static::$_data = $data;
    }

    /**
     * @use 检查依赖
     * @return bool
     */
    protected static function depend(): bool
    {
        if (getConf('server', 'heartbeat_enc') == '') {
            return false;
        }
        // 加载加密服务器
        if (is_null(static::$_enc_server)) {
            static::$_enc_server = getConf('server', 'heartbeat_enc');
        }
        return true;
    }

}