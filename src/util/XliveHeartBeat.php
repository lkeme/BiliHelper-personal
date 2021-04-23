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
use BiliHelper\Plugin\User;
use BiliHelper\Tool\Generator;

trait XliveHeartBeat
{

    protected static $_data = ['id' => []]; // data [ets, benchmark, time, secret_rule, id]  data->id [parent_area_id, area_id, 0, room_id]
    protected static $_secret_rule = []; // secret_rule [2, 3, 1, 5]
    protected static $_room_info = []; // 心跳房间信息

    protected static $_retry = 3; // 重试次数
    protected static $_count_num = 0; // 计数
    protected static $_count_time = 0; // 计时间

    protected static $_current_room_id = 0; // 当前运行的ROOM_ID
    protected static $_enc_server = null; // 加密服务器 依赖配置文件

    protected static $_default = 0; // 默认值


    /**
     * @use 重置变量
     * @param false $force
     */
    protected static function resetVar($force = false)
    {
        if ($force){
            self::$_room_info = [];
            self::$_current_room_id = 0;

            self::$_retry = 3;
            self::$_count_num = 0;
            self::$_count_time = 0;
        }
        $data = [
            'id' => self::$_data['id'],
        ];
        $data["id"][2] = 0;
        self::$_data = $data;
    }


    protected static function xliveHeartBeatTask(int $room_id, int $max_time, int $max_num)
    {
        // 加载依赖
        if (!self::depend()) {
            return self::$_default;
        }
        // 对比当前运行
        if (self::$_current_room_id != $room_id) {
            self::resetVar(true);
            self::$_current_room_id = $room_id;
        }
        // 加载房间信息
        if (empty(self::$_room_info)) {
            $r_data = Live::webGetRoomInfo($room_id);
            if ($r_data['code'] != 0) {
                Log::warning('直播间信息获取失败');
                return self::$_default;
            }
            $rdata = $r_data['data'];
            $parent_area_id = $rdata['room_info']['parent_area_id'];
            $area_id = $rdata['room_info']['area_id'];
            # 短位转长位
            $room_id = $rdata['room_info']['room_id'];
            self::$_data['id'] = [$parent_area_id, $area_id, 0, $room_id];
        }
        // 执行心跳
        $r_data = self::heartBeatIterator();
        $index = self::$_data['id'][2];
        if ($r_data['code'] != 0) {
            if (self::$_retry) {
                Log::warning("心跳失败-{$index} {$r_data['message']}");
                self::resetVar();
                self::$_retry -= 1;
                return self::$_default;
            }
        }
        self::$_count_num += 1;
        self::$_count_time += $r_data['heartbeat_interval'];

        // 最大次数限制
        if ($max_num <= self::$_count_num) {
            // 成功在id为{room_id}的直播间发送完{ii}次心跳，退出直播心跳(达到最大心跳次数)
        }
        // 最大时间限制
        if ($max_time <= self::$_count_time) {
            //成功在id为{room_id}的直播间发送第{ii}次心跳
        }
        $minute = round(self::$_count_time / 60);
        Log::info("已在直播间 {$room_id} 连续观看了 {$minute} 分钟");
        return $r_data['heartbeat_interval'];

    }

    /**
     * @use 检查依赖
     * @return bool
     */
    protected static function depend(): bool
    {
        if (getenv('ENC_SERVER') == '') {
            return false;
        }
        // 加载加密服务器
        if (is_null(self::$_enc_server)) {
            self::$_enc_server = getenv('ENC_SERVER');
        }
        return true;
    }


    protected static function heartBeatIterator(): array
    {
        $rdata = [];
        # 第1次执行 eHeartBeat
        if (self::$_data['id'][2] == 0) {
            $r_data = self::eHeartBeat(self::$_data['id']);
        } else {
            # 第1次之后执行 xHeartBeat
            self::$_data['ts'] = time() * 1000;
            self::$_data['s'] = self::encParamS(self::$_data, self::$_secret_rule);
            $r_data = self::xHeartBeat(self::$_data['id']);
        }
        if ($r_data['code'] == 0) {
            $rdata = $r_data['data'];
            self::$_data['ets'] = $rdata['timestamp'];
            self::$_data['benchmark'] = $rdata['secret_key'];
            self::$_data['time'] = $rdata['heartbeat_interval'];
            self::$_secret_rule = $rdata['secret_rule'];
            self::$_data['id'][2] += 1;
        }
        return [
            'code' => $r_data['code'],
            'message' => $r_data['message'],
            'heartbeat_interval' => $rdata['heartbeat_interval']
        ];
    }


    /**
     * @use E心跳
     * @param array $id
     * @return array|false[]
     */
    protected static function eHeartBeat(array $id): array
    {
        $url = 'https://live-trace.bilibili.com/xlive/data-interface/v1/x25Kn/E';
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin' => 'https://live.bilibili.com',
            'Referer' => 'https://live.bilibili.com/' . $id[3],
        ];
        $user_info = User::parseCookies();
        $payload = [
            'id' => json_encode([$id[0], $id[1], $id[2], $id[3]], true),
            'device' => json_encode([Generator::hash(), Generator::uuid4()], true),
            'ts' => time() * 1000,
            'is_patch' => 0,
            'heart_beat' => [],
            'ua' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:78.0) Gecko/20100101 Firefox/78.0',
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => ''
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        // {'code':0,'message':'0','ttl':1,'data':{'timestamp':1595342828,'heartbeat_interval':300,'secret_key':'seacasdgyijfhofiuxoannn','secret_rule':[2,5,1,4],'patch_status':2}}

        unset($payload['id']);
        self::$_data = array_merge_recursive(self::$_data, $payload);

        return json_decode($raw, true);
    }

    /**
     * @use X心跳
     * @param array $id
     * @return array|bool[]
     */
    protected static function xHeartBeat(array $id): array
    {
        $url = 'https://live-trace.bilibili.com/xlive/data-interface/v1/x25Kn/X';
        $user_info = User::parseCookies();
        $payload = [
            's' => self::$_data['s'],
            'id' => json_encode([$id[0], $id[1], $id[2], $id[3]], true),
            'device' => self::$_data['device'],
            'ets' => self::$_data['ets'],
            'benchmark' => self::$_data['benchmark'],
            'time' => self::$_data['time'],
            'ts' => self::$_data['ts'],
            'ua' => self::$_data['ua'],
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => ''
        ];
        // print_r($payload);
        $raw = Curl::post('pc', $url, $payload, self::$hb_headers);
        # {'code':0,'message':'0','ttl':1,'data':{'heartbeat_interval':300,'timestamp':1595346846,'secret_rule':[2,5,1,4],'secret_key':'seacasdgyijfhofiuxoannn'}}
        return json_decode($raw, true);
    }

    /**
     * @use 加密参数S
     * @param array $t
     * @param array $r
     * @return string|false
     */
    protected static function encParamS(array $t, array $r)
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];
        // 加密部分
        $payload = ['t' => $t, 'r' => $r];
        $data = Curl::put('other', self::$_enc_server, $payload, $headers);
        $de_raw = json_decode($data, true);
        if ($de_raw['code'] == 0) {
            // Log::info("S加密成功 {$de_raw['s']}");
            return $de_raw['s'];
        } else {
            Log::warning("S加密失败 {$de_raw['message']}");
            return false;
        }
    }


}