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
use BiliHelper\Util\BaseRaffle;

class PkRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '主播乱斗';
    const ACTIVE_SWITCH = 'live_pk';

    protected static array $wait_list = [];
    protected static array $finish_list = [];
    protected static array $all_list = [];

    /**
     * @use 解析数据
     * @param int $room_id
     * @param array $data
     * @return bool
     */
    protected static function parseLotteryInfo(int $room_id, array $data): bool
    {
        // 防止异常
        if (!array_key_exists('pk', $data['data'])) {
            return false;
        }
        $de_raw = $data['data']['pk'];
        if (empty($de_raw)) {
            return false;
        }

        foreach ($de_raw as $pk) {
            // 无效抽奖
            if ($pk['status'] != 1) {
                continue;
            }
            // 去重
            if (self::toRepeatLid($pk['id'])) {
                continue;
            }
            // 推入列表
            $data = [
                'room_id' => $room_id,
                'raffle_id' => $pk['id'],
                'raffle_name' => $pk['title'],
                'wait' => time() + mt_rand(5, 25)
            ];
            Statistics::addPushList($data['raffle_name']);
            self::$wait_list[] = $data;
        }
        return true;
    }

    /**
     * @use 创建抽奖任务
     * @param array $raffles
     * @return array
     */
    protected static function createLottery(array $raffles): array
    {
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/pk/join';
        $tasks = [];
        foreach ($raffles as $raffle) {
            $payload = [
                'id' => $raffle['raffle_id'],
                'roomid' => $raffle['room_id'],
                'csrf_token' => getCsrf(),
                "csrf" => getCsrf(),
            ];
            $tasks[] = [
                'payload' => Sign::common($payload),
                'source' => [
                    'room_id' => $raffle['room_id'],
                    'raffle_id' => $raffle['raffle_id'],
                    'raffle_name' => $raffle['raffle_name']
                ]
            ];
        }
        // print_r($results);
        return Curl::async('app', $url, $tasks);
    }

    /**
     * @use 解析抽奖信息
     * @param array $results
     * @return mixed
     */
    protected static function parseLottery(array $results): mixed
    {
        foreach ($results as $result) {
            $data = $result['source'];
            $content = $result['content'];
            $de_raw = json_decode($content, true);
            /*
             * {'code': 0, 'message': '0', 'ttl': 1, 'data': {'id': 343560, 'gift_type': 0, 'award_id': '1', 'award_text': '辣条X1', 'award_image': 'https://i0.hdslb.com/bfs/live/da6656add2b14a93ed9eb55de55d0fd19f0fc7f6.png', 'award_num': 0, 'title': '大乱斗获胜抽奖'}}
             * {'code': -1, 'message': '抽奖已结束', 'ttl': 1}
             * {'code': -2, 'message': '您已参加过抽奖', 'ttl': 1}
             * {"code":-403,"data":null,"message":"访问被拒绝","msg":"访问被拒绝"}
             */
            if (isset($de_raw['code']) && $de_raw['code'] == 0) {
                Statistics::addSuccessList($data['raffle_name']);
                $award_text = $de_raw['data']['award_text'];
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} {$data['raffle_name']}: $award_text");
                // 收益
                Statistics::addProfitList($data['raffle_name'] . '-' . explode('X', $award_text)[0], $de_raw['data']['award_num']);
            } elseif (isset($de_raw['msg']) && $de_raw['code'] == -403 && $de_raw['msg'] == '访问被拒绝') {
                Log::debug("房间 {$data['room_id']} 编号 {$data['raffle_id']} {$data['raffle_name']}: {$de_raw['message']}");
                self::pauseLock();
            } else {
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} {$data['raffle_name']}: {$de_raw['message']}");
            }
        }
        return '';
    }
}
