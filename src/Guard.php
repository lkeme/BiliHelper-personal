<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 20190731
 *  LastAPIChecked: 20190731
 */

namespace lkeme\BiliHelper;

class Guard
{
    const ACTIVE_TITLE = '总督舰长';
    const ACTIVE_SWITCH = 'USE_GUARD';

    public static $lock = 0;

    protected static $wait_list = [];
    protected static $all_list = [];

    public static function run()
    {
        if (getenv(self::ACTIVE_SWITCH) == 'false') {
            return;
        }
        if (self::$lock > time()) {
            return;
        }
        self::startLottery();
    }

    /**
     * 抽奖逻辑
     * @return bool
     */
    protected static function startLottery(): bool
    {
        $max_num = 3;
        while ($max_num) {
            $guard = array_shift(self::$wait_list);
            if (is_null($guard)) {
                break;
            }
            $guard_lid = $guard['lid'];
            $guard_rid = $guard['rid'];
            Live::goToRoom($guard_rid);
            Statistics::addJoinList(self::ACTIVE_TITLE);
            $data = self::lottery($guard_rid, $guard_lid);
            if ($data['code'] == 0) {
                Statistics::addSuccessList(self::ACTIVE_TITLE);
                Log::notice("房间 {$guard_rid} 编号 {$guard_lid} " . self::ACTIVE_TITLE . ": {$data['data']['message']}");
            } elseif ($data['code'] == 400 && $data['msg'] == '你已经领取过啦') {
                Log::info("房间 {$guard_rid} 编号 {$guard_lid} " . self::ACTIVE_TITLE . ": {$data['msg']}");
            } else {
                Log::warning("房间 {$guard_rid} 编号 {$guard_lid} " . self::ACTIVE_TITLE . ": {$data['msg']}");
            }
            $max_num--;
        }
        return true;
    }

    /**
     * 请求抽奖
     * @param $rid
     * @param $lid
     * @return array
     */
    private static function lottery($rid, $lid): array
    {
        $user_info = User::parseCookies();
        $url = "https://api.live.bilibili.com/lottery/v2/Lottery/join";
        $payload = [
            "roomid" => $rid,
            "id" => $lid,
            "type" => "guard",
            "csrf_token" => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => null,
        ];
        $raw = Curl::post($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
        return $de_raw;
    }

    /**
     * 重复检测
     * @param int $lid
     * @return bool
     */
    private static function toRepeatLid(int $lid): bool
    {
        if (in_array($lid, self::$all_list)) {
            return true;
        }
        if (count(self::$all_list) > 2000) {
            self::$all_list = [];
        }
        array_push(self::$all_list, $lid);

        return false;
    }

    /**
     * 数据推入队列
     * @param array $data
     * @return bool
     */
    public static function pushToQueue(array $data): bool
    {
        if (getenv(self::ACTIVE_SWITCH) == 'false') {
            return false;
        }
        if (self::toRepeatLid($data['lid'])) {
            return false;
        }
        Statistics::addPushList(self::ACTIVE_TITLE);
        self::$wait_list = array_merge(self::$wait_list, [['rid' => $data['rid'], 'lid' => $data['lid']]]);
        $wait_num = count(self::$wait_list);
        if ($wait_num > 2) {
            Log::info("当前队列中共有 {$wait_num} 个" . self::ACTIVE_TITLE . "待抽奖");
        }
        return true;
    }
}