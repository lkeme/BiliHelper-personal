<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace lkeme\BiliHelper;

class Anchor extends BaseRaffle
{
    const ACTIVE_TITLE = '天选时刻';
    const ACTIVE_SWITCH = 'USE_ANCHOR';

    public static $lock = 0;
    public static $rw_lock = 0;

    protected static $wait_list = [];
    protected static $finish_list = [];
    protected static $all_list = [];

    private static $filter_type = [];

    /**
     * 检查抽奖列表
     * @param $rid
     * @return bool
     */
    protected static function check($rid): bool
    {
        $payload = [
            'roomid' => $rid
        ];
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/Anchor/Check';
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);

        // 防止异常情况
        if (!isset($de_raw['data']) || $de_raw['data']['join_type'] || $de_raw['data']['lot_status']) {
            return false;
        }
        // TODO
        self::$filter_type = empty(self::$filter_type) ? explode(',', getenv('ANCHOR_TYPE')) : self::$filter_type;
        if (!in_array((string)$de_raw['data']['require_type'], self::$filter_type)) {
            return false;
        }

        $data = [
            'room_id' => $de_raw['data']['room_id'],
            'raffle_id' => $de_raw['data']['id'],
            'prize' => $de_raw['data']['award_name'],
            'wait' => strtotime(date("Y-m-d H:i:s"))
        ];
        if (static::toRepeatLid($data['raffle_id'])) {
            return false;
        }
        Statistics::addPushList(static::ACTIVE_TITLE);
        array_push(static::$wait_list, $data);
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
            'platform' => 'pc',
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => '',
        ];
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/Anchor/Join';
        $raw = Curl::post($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);

        if (isset($de_raw['code']) && $de_raw['code'] == 0) {
            print_r($de_raw);
            Statistics::addSuccessList(static::ACTIVE_TITLE);
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . static::ACTIVE_TITLE . ": {$data['prize']}");
        } else {
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . static::ACTIVE_TITLE . ": {$de_raw['message']}");
        }
        return true;
    }
}
