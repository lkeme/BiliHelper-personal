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

class GiftRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '活动礼物';
    const ACTIVE_SWITCH = 'USE_ACTIVE';

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
                'title' => $gift['title'],
                'type' => $gift['type'],
                'wait' => $gift['time_wait'] + time(),
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
            'raffleId' => $data['raffle_id'],
            'roomid' => $data['room_id'],
            'type' => $data['type'],
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => null,
        ];
        // V3接口 暂做保留处理
        // $url = 'https://api.live.bilibili.com/gift/v3/smalltv/join';
        // $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v5/smalltv/Join';
        $url = 'https://api.live.bilibili.com/gift/v4/smalltv/getAward';
        $raw = Curl::post($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
        if (isset($de_raw['code']) && !$de_raw['code']) {
            // 推送中奖信息
            if ($de_raw['data']['gift_name'] != '辣条' && $de_raw['data']['gift_name'] != '') {
                $info = $de_raw['data']['gift_name'] . 'x' . $de_raw['data']['gift_num'];
                Notice::push('gift', $info);
            }
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['data']['gift_name']}x{$de_raw['data']['gift_num']}");
            Statistics::addSuccessList(self::ACTIVE_TITLE);
        } else {
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['msg']}");
        }
        return true;
    }
}
