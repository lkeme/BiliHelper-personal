<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class GiftSend
{
    use TimeLock;

    protected static int $uid = 0;
    protected static int $tid = 0;
    protected static int $r_uid = 0;
    protected static int $room_id = 0;
    protected static int $short_id = 0;
    protected static array $room_list = [];
    protected static array $medal_list = [];

    /**
     * @use run
     */
    public static function run(): void
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
            // 减少0点左右请求损耗
            self::setLock(100);
        } else {
            self::setLock(self::timing(23, 55));
        }
    }

    /**
     * @use 方案1
     */
    protected static function procOne(): bool
    {
        if (!self::setTargetList()) {
            return false;
        }
        self::getMedalList();
        foreach (self::$medal_list as $room_id => $total_intimacy) {
            $bag_list = self::fetchBagList();
            if (!getConf('feed_fill', 'intimacy')) {
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
                // 是辣条、亿元 && 不是过期礼物 加入小心心，暂不清楚是否有逻辑冲突
                if (!in_array($gift['gift_id'], [1, 6, 30607])) {
                    continue;
                }
                Log::notice("直播间 $room_id 需赠送亲密度 $total_intimacy 剩余亲密度 " . ($total_intimacy - $current_intimacy));
                $amt = self::calcAmt($gift, $total_intimacy - $current_intimacy);
                self::sendGift($gift, $amt);
                $current_intimacy += ($gift['gift_id'] == 30607) ? ($amt * 50) : (($gift['gift_id'] == 6) ? ($amt * 10) : $amt);
                if (!($current_intimacy - $total_intimacy)) {
                    Log::notice("直播间 $room_id 亲密度 $total_intimacy 送满啦~送满啦~");
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
    protected static function procTwo(): void
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
        $temp = empty($temp = getConf('room_list', 'intimacy')) ? null : $temp;
        if (is_null($temp)) return false;
        self::$room_list = explode(',', $temp);
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
                $new_bag_list[] = $vo;
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
                $expire_gift_list[] = $gift;
            }
        }
        return $expire_gift_list;
    }

    /**
     * @use 获取勋章列表(过滤无勋章或已满)
     */
    protected static function getMedalList(): void
    {
        self::$medal_list = [];
        $data = Live::fetchMedalList();
        $fans_medals = [];
        foreach ($data as $vo) {
            // 过滤主站勋章
            if (!isset($vo['roomid']) || $vo['roomid'] == 0) continue;
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
    protected static function getUserInfo(): void
    {
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/user/get_user_info';
        $payload = [];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('获取帐号信息失败!', ['msg' => $data['message']]);
            Log::warning('清空礼物功能禁用!');
            self::setLock(100000000);
            return;
        }
        self::$uid = $data['data']['uid'];
    }

    /**
     * @use 获取直播间信息
     */
    protected static function getRoomInfo(): void
    {
        Log::info('正在生成直播间信息...');
        $room_id = empty(self::$tid) ? getConf('room_id', 'global_room') : self::$tid;
        $data = Live::getRoomInfoV1($room_id);
        if (isset($data['code']) && $data['code']) {
            Log::warning('获取主播房间号失败!', ['msg' => $data['message']]);
            Log::warning('清空礼物功能禁用!');
            self::setLock(100000000);
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
        if ($gift['gift_id'] == 30607) {
            $amt = (floor($surplus_num / 50) > $gift['gift_num']) ? $gift['gift_num'] : floor($surplus_num / 50);
        }
        return ($amt < 1) ? 1 : $amt;
    }

    /**
     * @use 赠送礼物
     * @param array $value
     * @param int $amt
     */
    protected static function sendGift(array $value, int $amt): void
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
            Log::notice("成功向 {$payload['biz_id']} 投喂了 $amt 个{$value['gift_name']}");
        }
    }
}
