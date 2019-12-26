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

class GiftSend
{
    use TimeLock;
    protected static $uid = 0;
    protected static $tid = 0;
    protected static $r_uid = 0;
    protected static $room_id = 0;
    protected static $room_list = [];
    protected static $medal_list = [];


    public static function run()
    {
        if (self::getLock() > time()) {
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
        self::setLock(5 * 60);
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
        foreach (self::$medal_list as $key => $val) {
            $bag_list = self::fetchBagList();
            array_multisort(array_column($bag_list, "expire_at"), SORT_ASC, $bag_list);
            if (getenv('FEED_FILL') == 'false') {
                $bag_list = self::checkExpireGift($bag_list);
            }
            if (count($bag_list)) {
                self::$tid = $key;
                self::getRoomInfo();
            } else {
                break;
            }
            foreach ($bag_list as $gift) {
                // 是辣条、亿元 && 不是过期礼物
                if (!in_array($gift['gift_id'], [1, 6]) && getenv('FEED_FILL') != 'false') {
                    continue;
                }
                $amt = self::calcAmt($gift);
                self::sendGift($gift, $amt);
                $val -= $amt;
                if (!$val) {
                    Log::notice("直播间 {$key} 亲密度 {$val} 送满啦~送满啦~");
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
        $bag_list = [];
        $payload = [];
        $data = Curl::get('https://api.live.bilibili.com/gift/v2/gift/bag_list', Sign::api($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('背包查看失败!', ['msg' => $data['message']]);
            return $bag_list;
        }
        if (isset($data['data']['list'])) {
            $bag_list = $data['data']['list'];
            array_multisort(array_column($bag_list, "gift_id"), SORT_DESC, $bag_list);
            foreach ($bag_list as $vo) {
                // 去除永久礼物
                if ($vo['corner_mark'] == '永久') {
                    continue;
                }
                array_push($bag_list, $vo);
            }
        }
        return $bag_list;
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
        Log::info('正在获取勋章列表...');
        $payload = [];
        $data = Curl::get('https://api.live.bilibili.com/i/api/medal?page=1&pageSize=25', Sign::api($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('获取勋章列表失败!', ['msg' => $data['message']]);
            return;
        }
        Log::info('勋章列表获取成功!');
        if (isset($data['data']['fansMedalList'])) {
            foreach ($data['data']['fansMedalList'] as $vo) {
                if (in_array($vo['roomid'], self::$room_list) && ($vo['day_limit'] - $vo['today_feed'])) {
                    self::$medal_list[(string)$vo['roomid']] = ($vo['day_limit'] - $vo['today_feed']);
//                    $data = [
//                        $vo['roomid'] => ($vo['day_limit'] - $vo['today_feed'])
//                    ];
//                    array_push(self::$medal_list, $data);
                }
            }
        }
    }


    /**
     * @use 获取UID
     */
    protected static function getUserInfo()
    {
        $payload = [];
        $data = Curl::get('https://api.live.bilibili.com/xlive/web-ucenter/user/get_user_info', Sign::api($payload));
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
        $payload = [
            'id' => empty(self::$tid) ? getenv('ROOM_ID') : self::$tid,
        ];
        $data = Curl::get('https://api.live.bilibili.com/room/v1/Room/room_init', Sign::api($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('获取主播房间号失败!', ['msg' => $data['message']]);
            Log::warning('清空礼物功能禁用!');
            self::$lock = time() + 100000000;
            return;
        }
        Log::info('直播间信息生成完毕!');
        self::$r_uid = (string)$data['data']['uid'];
        self::$room_id = (string)$data['data']['room_id'];
    }


    /**
     * @use 计算赠送数量
     * @param array $gift
     * @return int
     */
    protected static function calcAmt(array $gift): int
    {
        $amt = $gift['gift_num'];
        if ($gift['gift_id'] == 1) {
            $amt = (self::$medal_list[self::$room_id] > $gift['gift_num']) ? $gift['gift_num'] : self::$medal_list[self::$room_id];
        }
        if ($gift['gift_id'] == 6) {
            $amt = (floor(self::$medal_list[self::$room_id] / 10) > $gift['gift_num']) ? $gift['gift_num'] : floor(self::$medal_list[self::$room_id] / 10);
        }
        return $amt;
    }


    /**
     * @use 赠送礼物
     * @param array $value
     * @param int $amt
     */
    protected static function sendGift(array $value, int $amt)
    {
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
        $data = Curl::post('https://api.live.bilibili.com/gift/v2/live/bag_send', Sign::api($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('送礼失败!', ['msg' => $data['message']]);
        } else {
            Log::notice("成功向 {$payload['biz_id']} 投喂了 {$amt} 个{$value['gift_name']}");
        }
    }
}
