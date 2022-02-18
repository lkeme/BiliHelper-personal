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

class GuardRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '总督舰长';
    const ACTIVE_SWITCH = 'live_guard';

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
        if (!array_key_exists('guard', $data['data'])) {
            return false;
        }
        $de_raw = $data['data']['guard'];
        if (empty($de_raw)) {
            return false;
        }

        foreach ($de_raw as $guard) {
            // 无效抽奖
            if ($guard['status'] != 1) {
                continue;
            }
            // 去重
            if (self::toRepeatLid($guard['id'])) {
                continue;
            }
            // 获取等级名称
//            switch ($guard['privilege_type']) {
//                case 1:
//                    $raffle_name = '总督';
//                    break;
//                case 2:
//                    $raffle_name = '提督';
//                    break;
//                case 3:
//                    $raffle_name = '舰长';
//                    break;
//                default:
//                    $raffle_name = '舰队';
//                    break;
//            }

            // 推入列表
            $data = [
                'room_id' => $room_id,
                'raffle_id' => $guard['id'],
                'raffle_name' => $guard['gift_name'],
                'wait' => time() + mt_rand(5, 30)
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
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v3/guard/join';
        $tasks = [];
        foreach ($raffles as $raffle) {
            $payload = [
                'id' => $raffle['raffle_id'],
                'roomid' => $raffle['room_id'],
                "type" => "guard",
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
            // {"code":-403,"data":null,"message":"访问被拒绝","msg":"访问被拒绝"}
            if (isset($de_raw['code']) && $de_raw['code'] == 0) {
                Statistics::addSuccessList($data['raffle_name']);
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} {$data['raffle_name']}: " . $de_raw['data']['award_name'] . "x" . $de_raw['data']['award_num']);
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