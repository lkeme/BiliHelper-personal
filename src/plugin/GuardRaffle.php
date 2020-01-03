<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class GuardRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '总督舰长';
    const ACTIVE_SWITCH = 'USE_GUARD';

    use TimeLock;

    protected static $wait_list = [];
    protected static $finish_list = [];
    protected static $all_list = [];


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
            switch ($guard['privilege_type']) {
                case 1:
                    $raffle_name = '总督';
                    break;
                case 2:
                    $raffle_name = '提督';
                    break;
                case 3:
                    $raffle_name = '舰长';
                    break;
                default:
                    $raffle_name = '舰队';
                    break;
            }
            // 推入列表
            $data = [
                'room_id' => $room_id,
                'raffle_id' => $guard['id'],
                'raffle_name' => $raffle_name,
                'wait' => time()
            ];
            Statistics::addPushList(self::ACTIVE_TITLE);
            array_push(self::$wait_list, $data);
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
        $results = [];
        $user_info = User::parseCookies();
        foreach ($raffles as $raffle) {
            $payload = [
                'id' => $raffle['raffle_id'],
                'roomid' => $raffle['room_id'],
                "type" => "guard",
                'csrf_token' => $user_info['token'],
                'csrf' => $user_info['token'],
                'visit_id' => ''
            ];
            array_push($tasks, [
                'payload' => Sign::api($payload),
                'source' => [
                    'room_id' => $raffle['room_id'],
                    'raffle_id' => $raffle['raffle_id']
                ]
            ]);
        }
        $results = Curl::asyncPost($url, $tasks);
        # print_r($results);
        return $results;
    }

    /**
     * @use 解析抽奖信息
     * @param array $results
     * @return mixed|void
     */
    protected static function parseLottery(array $results)
    {
        foreach ($results as $result) {
            $data = $result['source'];
            $content = $result['content'];
            $de_raw = json_decode($content, true);
            if (isset($de_raw['code']) && $de_raw['code'] == 0) {
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": " . $de_raw['data']['award_name'] . "x" . $de_raw['data']['award_num']);
                Statistics::addSuccessList(self::ACTIVE_TITLE);
            } else {
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['msg']}");
            }
        }
    }

}