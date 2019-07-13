<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class RaffleHandler
{
    const KEY = '统一活动';
    const SWITCH = 'USE_ACTIVE';

    public static $lock = 0;
    public static $rw_lock = 0;

    private static $wait_list = [];
    private static $finsh_list = [];
    private static $all_list = [];

    public static function run()
    {
        if (getenv(self::SWITCH) == 'false') {
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
        $max_num = mt_rand(5, 10);
        while ($max_num) {
            $raffle = array_shift(self::$wait_list);
            if (is_null($raffle)) {
                break;
            }
            Live::goToRoom($raffle['room_id']);
            Statistics::addJoinList(self::KEY);
            self::lottery($raffle);
            $max_num--;
        }
        return true;
    }


    /**
     * 检查抽奖列表
     * @param $rid
     * @return bool
     */
    private static function checkWeb($rid): bool
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
            if (self::toRepeatLid($data['raffle_id'])) {
                continue;
            }
            Statistics::addPushList(self::KEY);
            array_push(self::$wait_list, $data);
        }
        return true;
    }


    /**
     * @use WEB中奖查询
     */
    public static function resultWeb()
    {
        // 时间锁
        if (self::$rw_lock > time()) {
            return;
        }
        // 如果待查询为空 && 去重
        if (!count(self::$finsh_list)) {
            self::$rw_lock = time() + 40;
            return;
        }
        // 查询，每次查询10个
        $flag = 0;
        foreach (self::$finsh_list as $winning_web) {
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
                    Statistics::addSuccessList(self::KEY);
                    // 提示信息
                    $info = "房间 {$winning_web['room_id']} 编号 {$winning_web['raffle_id']} {$winning_web['title']}: 获得";
                    $info .= "{$de_raw['data']['gift_name']}X{$de_raw['data']['gift_num']}";
                    Log::notice($info);
                    // 推送活动抽奖信息
                    if ($de_raw['data']['gift_name'] != '辣条' && $de_raw['data']['gift_name'] != '') {
                        Notice::run('raffle', $info);
                    }
                    // 删除查询完成ID
                    unset(self::$finsh_list[$flag - 1]);
                    self::$finsh_list = array_values(self::$finsh_list);
                    break;
                default:
                    break;
            }
        }
        self::$rw_lock = time() + 40;
        return;
    }


    /**
     * @use 请求抽奖
     * @param array $data
     */
    private static function lottery(array $data)
    {
        $payload = [
            'raffleId' => $data['raffle_id'],
            'roomid' => $data['room_id'],
        ];
        $url = 'https://api.live.bilibili.com/gift/v3/smalltv/join';
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
        if (isset($de_raw['code']) && $de_raw['code']) {
            Log::notice("房间 {$data['raffle_id']} 编号 {$data['room_id']} " . self::KEY . ": {$de_raw['message']}");
        } else {
            Log::notice("房间 {$data['raffle_id']} 编号 {$data['room_id']} " . self::KEY . ": {$de_raw['msg']}");
            array_push(self::$finsh_list, $data);
        }
        return;
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
        if (getenv(self::SWITCH) == 'false') {
            return false;
        }

        if (Live::fishingDetection($data['rid'])) {
            return false;
        }
        self::checkWeb($data['rid']);
        $wait_num = count(self::$wait_list);
        if ($wait_num > 2) {
            Log::info("当前队列中共有 {$wait_num} 个" . self::KEY . "待抽奖");
        }
        return true;
    }

}
