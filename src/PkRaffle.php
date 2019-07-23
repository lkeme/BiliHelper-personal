<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class PkRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '大乱斗';
    const ACTIVE_SWITCH = 'USE_PK';

    public static $lock = 0;
    public static $rw_lock = 0;

    protected static $wait_list = [];
    protected static $finish_list = [];
    protected static $all_list = [];

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
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/pk/check';
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);

        // 计数 && 跳出
        $total = count($de_raw['data']);
        if (!$total) {
            return false;
        }

        for ($i = 0; $i < $total; $i++) {
            $data = [
                'raffle_id' => $de_raw['data'][$i]['pk_id'],
                'title' => $de_raw['data'][$i]['title'],
                'room_id' => $de_raw['data'][$i]['room_id']
            ];
            if (static::toRepeatLid($data['raffle_id'])) {
                continue;
            }
            Statistics::addPushList(static::ACTIVE_TITLE);
            array_push(static::$wait_list, $data);
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
            'csrf_token' => $user_info['token'],
            "csrf" => $user_info['token'],
        ];
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/pk/join';
        $raw = Curl::post($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
        /*
         * {'code': 0, 'message': '0', 'ttl': 1, 'data': {'id': 343560, 'gift_type': 0, 'award_id': '1', 'award_text': '辣条X1', 'award_image': 'https://i0.hdslb.com/bfs/live/da6656add2b14a93ed9eb55de55d0fd19f0fc7f6.png', 'award_num': 0, 'title': '大乱斗获胜抽奖'}}
         * {'code': -1, 'message': '抽奖已结束', 'ttl': 1}
         * {'code': -2, 'message': '您已参加过抽奖', 'ttl': 1}
         * {"code":-403,"data":null,"message":"访问被拒绝","msg":"访问被拒绝"}
         */
        if (isset($de_raw['code']) && $de_raw['code'] == 0) {
            Statistics::addSuccessList(static::ACTIVE_TITLE);
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . static::ACTIVE_TITLE . ": {$de_raw['data']['award_text']}");
        } else {
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . static::ACTIVE_TITLE . ": {$de_raw['message']}");
        }
        return true;
    }
}
