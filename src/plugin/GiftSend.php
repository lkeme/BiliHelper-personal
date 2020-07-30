<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;


use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class GiftSend
{
    use TimeLock;

    protected static $uid = 0;
    protected static $tid = 0;
    protected static $r_uid = 0;
    protected static $room_id = 0;
    protected static $short_id = 0;
    protected static $room_list = [];
    protected static $medal_list = [];


    public static function run()
    {
        if (self::getLock() > time() || !self::inTime('23:50:00', '23:59:50')) {
            return;
        }
        if (!self::$uid) {
            self::getUserInfo();
        }
        // 方案一未通过使用方案2
        if (!self::procOne()) {
            self::procTwo();
        }
        self::$room_list = [];
        self::$medal_list = [];
        self::$tid = 0;
        // 如果在每日最后5分钟内 就50s执行一次 否则 第二天固定时间执行
        if (self::inTime('23:52:00', '23:59:59')) {
            self::setLock(60);
        } else {
            self::setLock(self::timing(23, 55));
        }
    }


    /**
     * @use 方案1
     */
    protected static function procOne()
    {
        if (!self::setTargetList()) {
            return false;
        }
        self::getMedalList();
        foreach (self::$medal_list as $room_id => $total_intimacy) {
            $bag_list = self::fetchBagList();
            if (getenv('FEED_FILL') == 'false') {
                $bag_list = self::checkExpireGift($bag_list);
            }
            if (count($bag_list)) {
                self::$tid = $room_id;
                self::getRoomInfo();
                // array_multisort(array_column($bag_list, "expire_at"), SORT_ASC, $bag_list);
            } else {
                break;
            }
            $current_intimacy = 0;
            foreach ($bag_list as $gift) {
                // 是辣条、亿元 && 不是过期礼物
                if (!in_array($gift['gift_id'], [1, 6])) {
                    continue;
                }
                Log::notice("直播间 {$room_id} 需赠送亲密度 {$total_intimacy} 剩余亲密度 " . ($total_intimacy - $current_intimacy));
                $amt = self::calcAmt($gift, $total_intimacy - $current_intimacy);
                self::sendGift($gift, $amt);
                $current_intimacy += ($gift['gift_id'] == 6) ? ($amt * 10) : $amt;
                if (!($current_intimacy - $total_intimacy)) {
                    Log::notice("直播间 {$room_id} 亲密度 {$total_intimacy} 送满啦~送满啦~");
                    break;
                }
            }
        }
        if (!count(self::$medal_list)) {
            return false;
        }
        return true;
    }

    /**
     * @use 方案2
     */
    protected static function procTwo()
    {
        $bag_list = self::fetchBagList();
        $expire_gift = self::checkExpireGift($bag_list);
        if (count($expire_gift)) {
            self::getRoomInfo();
            foreach ($expire_gift as $gift) {
                self::sendGift($gift, $gift['gift_num']);
            }
        }
    }

    /**
     * @use 设置房间列表
     * @return bool
     */
    protected static function setTargetList(): bool
    {
        $temp = empty(getenv('ROOM_LIST')) ? null : getenv('ROOM_LIST');
        if (is_null($temp)) return false;
        self::$room_list = explode(',', getenv('ROOM_LIST'));
        return true;
    }


    /**
     * @use 获取背包列表
     * @return array
     */
    protected static function fetchBagList(): array
    {
        $new_bag_list = [];
        $payload = [];
        $url = 'https://api.live.bilibili.com/gift/v2/gift/bag_list';
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('背包查看失败!', ['msg' => $data['message']]);
            return $new_bag_list;
        }
        if (isset($data['data']['list'])) {
            $bag_list = $data['data']['list'];
            if (count($bag_list)) {
                // 按过期时间 升序
                // array_multisort(array_column($bag_list, "gift_id"), SORT_DESC, $bag_list);
                array_multisort(array_column($bag_list, "expire_at"), SORT_ASC, $bag_list);
            }
            foreach ($bag_list as $vo) {
                // 去除永久礼物
                if ($vo['corner_mark'] == '永久') {
                    continue;
                }
                array_push($new_bag_list, $vo);
            }
        }
        return $new_bag_list;
    }


    /**
     * @use 查找过期礼物
     * @param array $bag_list
     * @return array
     */
    protected static function checkExpireGift(array $bag_list): array
    {
        $expire_gift_list = [];
        foreach ($bag_list as $gift) {
            if ($gift['expire_at'] >= time() && $gift['expire_at'] <= time() + 3600) {
                array_push($expire_gift_list, $gift);
            }
        }
        return $expire_gift_list;
    }


    /**
     * @use 获取勋章列表(过滤无勋章或已满)
     */
    protected static function getMedalList()
    {
        self::$medal_list = [];
        $data = Live::fetchMedalList();
        $fans_medals = [];
        foreach ($data as $vo) {
            if (!isset($vo['roomid'])) continue;
            $fans_medals[(string)$vo['roomid']] = $vo;
        }
        // 基于配置
        foreach (self::$room_list as $room_id) {
            // 配置是否存在获取
            if (!array_key_exists((string)$room_id, $fans_medals)) {
                continue;
            }
            $vo = $fans_medals[(string)$room_id];
            // 是否还需要投喂
            if ($vo['day_limit'] - $vo['today_feed']) {
                self::$medal_list[(string)$vo['roomid']] = ($vo['day_limit'] - $vo['today_feed']);
            }
        }
    }


    /**
     * @use 获取UID
     */
    protected static function getUserInfo()
    {
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/user/get_user_info';
        $payload = [];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('获取帐号信息失败!', ['msg' => $data['message']]);
            Log::warning('清空礼物功能禁用!');
            self::$lock = time() + 100000000;
            return;
        }
        self::$uid = $data['data']['uid'];
    }

    /**
     * @use 获取直播间信息
     */
    protected static function getRoomInfo()
    {
        Log::info('正在生成直播间信息...');
        $room_id = empty(self::$tid) ? getenv('ROOM_ID') : self::$tid;
        $data = Live::getRoomInfo($room_id);
        if (isset($data['code']) && $data['code']) {
            Log::warning('获取主播房间号失败!', ['msg' => $data['message']]);
            Log::warning('清空礼物功能禁用!');
            self::$lock = time() + 100000000;
            return;
        }
        Log::info('直播间信息生成完毕!');
        self::$r_uid = (string)$data['data']['uid'];
        self::$room_id = (string)$data['data']['room_id'];
        self::$short_id = $data['data']['short_id'] ? (string)$data['data']['short_id'] : self::$room_id;
    }


    /**
     * @use 计算赠送数量
     * @param array $gift
     * @param int $surplus_num
     * @return int
     */
    protected static function calcAmt(array $gift, int $surplus_num): int
    {
        $amt = $gift['gift_num'];
        if ($gift['gift_id'] == 1) {
            $amt = ($surplus_num > $gift['gift_num']) ? $gift['gift_num'] : floor($surplus_num);
        }
        if ($gift['gift_id'] == 6) {
            $amt = (floor($surplus_num / 10) > $gift['gift_num']) ? $gift['gift_num'] : floor($surplus_num / 10);
        }
        return ($amt < 1) ? 1 : $amt;
    }


    /**
     * @use 赠送礼物
     * @param array $value
     * @param int $amt
     */
    protected static function sendGift(array $value, int $amt)
    {
        $url = 'https://api.live.bilibili.com/gift/v2/live/bag_send';
        $payload = [
            'coin_type' => 'silver',
            'gift_id' => $value['gift_id'],
            'ruid' => self::$r_uid,
            'uid' => self::$uid,
            'biz_id' => self::$room_id,
            'gift_num' => $amt,
            'data_source_id' => '',
            'data_behavior_id' => '',
            'bag_id' => $value['bag_id']
        ];
        $data = Curl::post('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('送礼失败!', ['msg' => $data['message']]);
        } else {
            Log::notice("成功向 {$payload['biz_id']} 投喂了 {$amt} 个{$value['gift_name']}");
        }
    }
}
