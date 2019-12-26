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

class AnchorRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '天选时刻';
    const ACTIVE_SWITCH = 'USE_ANCHOR';

    use TimeLock;

    protected static $wait_list = [];
    protected static $finish_list = [];
    protected static $all_list = [];

    private static $filter_type = [];


    /**
     * @use 解析数据
     * @param int $room_id
     * @param array $data
     * @return bool
     */
    protected static function parse(int $room_id, array $data): bool
    {
        // 防止异常
        if (!array_key_exists('anchor', $data['data'])) {
            return false;
        }
        $de_raw = $data['data']['anchor'];
        if (empty($de_raw)) {
            return false;
        }
        // 无效抽奖
        if ($de_raw['join_type'] || $de_raw['lot_status']) {
            return false;
        }
        // 过滤抽奖范围
        self::$filter_type = empty(self::$filter_type) ? explode(',', getenv('ANCHOR_TYPE')) : self::$filter_type;
        if (!in_array((string)$de_raw['require_type'], self::$filter_type)) {
            return false;
        }
        // 去重
        if (self::toRepeatLid($de_raw['id'])) {
            return false;
        }
        // 推入列表
        $data = [
            'room_id' => $room_id,
            'raffle_id' => $de_raw['id'],
            'raffle_name' => $de_raw['award_name'],
            'wait' => time()
        ];
        Statistics::addPushList(self::ACTIVE_TITLE);
        array_push(self::$wait_list, $data);
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
            Statistics::addSuccessList(self::ACTIVE_TITLE);
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$data['raffle_name']}");
        } else {
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['message']}");
        }
        return true;
    }
}
