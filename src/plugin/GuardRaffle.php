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
    protected static function parse(int $room_id, array $data): bool
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
     * @use 请求抽奖
     * @param array $data
     * @return bool
     */
    protected static function lottery(array $data): bool
    {
        $user_info = User::parseCookies();
        $payload = [
            'id' => $data['raffle_id'],
            'roomid' => $data['room_id'],
            "type" => "guard",
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token']
        ];
        $url = 'https://api.live.bilibili.com/lottery/v2/lottery/join';
        $raw = Curl::post($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);

        if (isset($de_raw['code']) && $de_raw['code'] == 0) {
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['data']['message']}");
            Statistics::addSuccessList(self::ACTIVE_TITLE);
        } else {
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['msg']}");
        }
        return true;
    }


}