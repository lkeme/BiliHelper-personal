<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class UnifyRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '统一活动';
    const ACTIVE_SWITCH = 'USE_ACTIVE';

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
        $url = 'https://api.live.bilibili.com/gift/v3/smalltv/check';
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);

        // 计数 && 跳出
        $total = count($de_raw['data']['list']);
        if (!$total) {
            return false;
        }

        for ($i = 0; $i < $total; $i++) {
            /**
             * raffleId    :    88995
             * title    :    C位光环抽奖
             * type    :    GIFT_30013
             */
            $data = [
                'raffle_id' => $de_raw['data']['list'][$i]['raffleId'],
                'title' => $de_raw['data']['list'][$i]['title'],
                'type' => $de_raw['data']['list'][$i]['type'],
                'room_id' => $rid
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
     * @use WEB中奖查询
     */
    public static function resultWeb()
    {
        // 时间锁
        if (static::$rw_lock > time()) {
            return;
        }
        // 如果待查询为空 && 去重
        if (!count(static::$finish_list)) {
            static::$rw_lock = time() + 40;
            return;
        }
        // 查询，每次查询10个
        $flag = 0;
        foreach (static::$finish_list as $winning_web) {
            $flag++;
            if ($flag > 40) {
                break;
            }
            // 参数
            $payload = [
                'type' => $winning_web['type'],
                'raffleId' => $winning_web['raffle_id']
            ];
            // Web V3 Notice
            $url = 'https://api.live.bilibili.com/gift/v3/smalltv/notice';
            // 请求 && 解码
            $raw = Curl::get($url, Sign::api($payload));
            $de_raw = json_decode($raw, true);
            // 判断
            switch ($de_raw['data']['status']) {
                case 3:
                    break;
                case 2:
                    Statistics::addSuccessList(static::ACTIVE_TITLE);
                    // 提示信息
                    $info = "房间 {$winning_web['room_id']} 编号 {$winning_web['raffle_id']} {$winning_web['title']}: 获得";
                    $info .= "{$de_raw['data']['gift_name']}X{$de_raw['data']['gift_num']}";
                    Log::notice($info);
                    // 推送活动抽奖信息
                    if ($de_raw['data']['gift_name'] != '辣条' && $de_raw['data']['gift_name'] != '') {
                        Notice::run('raffle', $info);
                    }
                    // 删除查询完成ID
                    unset(static::$finish_list[$flag - 1]);
                    static::$finish_list = array_values(static::$finish_list);
                    break;
                default:
                    break;
            }
        }
        static::$rw_lock = time() + 40;
        return;
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
            'type' => 'Gift',
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => null,
        ];
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v3/smalltv/join';
        $raw = Curl::post($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
        if (isset($de_raw['code']) && $de_raw['code']) {
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . static::ACTIVE_TITLE . ": {$de_raw['message']}");
        } else {
            Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . static::ACTIVE_TITLE . ": {$de_raw['msg']}");
            array_push(static::$finish_list, $data);
        }
        return true;
    }
}
