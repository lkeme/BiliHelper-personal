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

class GiftRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '活动礼物';
    const ACTIVE_SWITCH = 'live_gift';

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
        if (!array_key_exists('gift_list', $data['data'])) {
            return false;
        }
        $de_raw = $data['data']['gift_list'];
        if (empty($de_raw)) {
            return false;
        }
        foreach ($de_raw as $gift) {
            // 无效抽奖
            if ($gift['status'] != 1) {
                continue;
            }
            // 去重
            if (self::toRepeatLid($gift['raffleId'])) {
                continue;
            }
            $data = [
                'room_id' => $room_id,
                'raffle_id' => $gift['raffleId'],
                'raffle_name' => $gift['title'],
                'type' => $gift['type'],
                'wait' => $gift['time_wait'] + time(),
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
        // V3接口 暂做保留处理
        // $url = 'https://api.live.bilibili.com/gift/v3/smalltv/join';
        // $url = 'https://api.live.bilibili.com/gift/v4/smalltv/getAward';
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v5/smalltv/join';
        $tasks = [];
        foreach ($raffles as $raffle) {
            $payload = [
                'id' => $raffle['raffle_id'],
                'roomid' => $raffle['room_id'],
                'type' => $raffle['type'],
                'csrf_token' => getCsrf(),
                'csrf' => getCsrf(),
                'visit_id' => ''
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
            // { "code": -403, "data": null, "message": "访问被拒绝", "msg": "访问被拒绝", }
            if (isset($de_raw['code']) && !$de_raw['code']) {
                // 推送中奖信息
                if ($de_raw['data']['award_name'] != '辣条' && $de_raw['data']['award_name'] != '') {
                    $info = $de_raw['data']['award_name'] . 'x' . $de_raw['data']['award_num'];
                    Notice::push('gift', $info);
                }
                Statistics::addSuccessList($data['raffle_name']);
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} {$data['raffle_name']}: {$de_raw['data']['award_name']}x{$de_raw['data']['award_num']}");
                Statistics::addProfitList($data['raffle_name'] . '-' . $de_raw['data']['award_name'], $de_raw['data']['award_num']);
            } elseif (isset($de_raw['msg']) && $de_raw['code'] == -403 && $de_raw['msg'] == '访问被拒绝') {
                Log::debug("房间 {$data['room_id']} 编号 {$data['raffle_id']} {$data['raffle_name']}: {$de_raw['msg']}");
                self::pauseLock();
            } else {
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} {$data['raffle_name']}: " . isset($de_raw['msg']) ? $de_raw['msg'] : $de_raw);
            }
        }
        return '';
    }
}
