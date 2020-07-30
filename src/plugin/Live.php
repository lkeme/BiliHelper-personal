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

class Live
{
    use TimeLock;

    /**
     * @use 获取分区列表
     * @return array
     */
    public static function fetchLiveAreas(): array
    {
        $areas = [];
        $url = "http://api.live.bilibili.com/room/v1/Area/getList";
        $payload = [];
        $raw = Curl::get('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        // 防止异常
        if (!isset($de_raw['data']) || $de_raw['code']) {
            Log::warning("获取直播分区异常: " . $de_raw['msg']);
            $areas = range(1, 6);
        } else {
            foreach ($de_raw['data'] as $area) {
                array_push($areas, $area['id']);
            }
        }
        return $areas;
    }


    /**
     * @use AREA_ID转ROOM_ID
     * @param $area_id
     * @return array
     */
    public static function areaToRid($area_id): array
    {
        $url = "https://api.live.bilibili.com/room/v1/area/getRoomList";
        $payload = [
            'platform' => 'web',
            'parent_area_id' => $area_id,
            'cate_id' => 0,
            'area_id' => 0,
            'sort_type' => 'online',
            'page' => 1,
            'page_size' => 30
        ];
        $raw = Curl::get('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        // 防止异常
        if (!isset($de_raw['data']) || $de_raw['code'] || count($de_raw['data']) == 0) {
            Log::warning("获取直播分区异常: " . $de_raw['msg']);
            $area_info = [
                'area_id' => $area_id,
                'room_id' => 23058
            ];
        } else {
            $area_info = [
                'area_id' => $area_id,
                'room_id' => $de_raw['data'][0]['roomid']
            ];
        }
        return $area_info;
    }


    /**
     * @use 获取随机直播房间号
     * @return int
     */
    public static function getUserRecommend()
    {
        $url = 'https://api.live.bilibili.com/room/v1/Area/getListByAreaID';
        $payload = [
            'areaId' => 0,
            'sort' => 'online',
            'pageSize' => 30,
            'page' => 1
        ];
        $raw = Curl::get('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] != '0') {
            return 23058;
        }
        return $de_raw['data'][mt_rand(1, 29)]['roomid'];
    }


    /**
     * @use 获取直播房间号
     * @param $room_id
     * @return bool
     */
    public static function getRealRoomID($room_id)
    {
        $data = self::getRoomInfo($room_id);
        if (!isset($data['code']) || !isset($data['data'])) {
            return false;
        }
        if ($data['code']) {
            Log::warning($room_id . ' : ' . $data['msg']);
            return false;
        }
        if ($data['data']['is_hidden']) {
            return false;
        }
        if ($data['data']['is_locked']) {
            return false;
        }
        if ($data['data']['encrypted']) {
            return false;
        }
        return $data['data']['room_id'];
    }

    /**
     * @use 获取直播间信息
     * @param $room_id
     * @return array
     */
    public static function getRoomInfo($room_id): array
    {
        $url = 'https://api.live.bilibili.com/room/v1/Room/room_init';
        $payload = [
            'id' => $room_id
        ];
        $raw = Curl::get('other', $url, $payload);
        return json_decode($raw, true);
    }

    /**
     * @use 获取弹幕配置
     * @param $room_id
     * @return array
     */
    public static function getDanMuConf($room_id): array
    {
        $url = 'https://api.live.bilibili.com/room/v1/Danmu/getConf';
        $payload = [
            'room_id' => $room_id,
            'platform' => 'pc',
            'player' => 'web'
        ];
        $raw = Curl::get('other', $url, $payload);
        return json_decode($raw, true);
    }


    /**
     * @use 获取配置信息
     * @param $room_id
     * @return array
     */
    public static function getDanMuInfo($room_id): array
    {
        $data = self::getDanMuConf($room_id);
        if (isset($data['data']['host_server_list'][0]['host'])) {
            $server = $data['data']['host_server_list'][0];
            $addr = "tcp://{$server['host']}:{$server['port']}/sub";
        } else {
            $addr = getenv('ZONE_SERVER_ADDR');
        }
        return [
            'addr' => $addr,
            'token' => isset($data['data']['token']) ? $data['data']['token'] : '',
        ];
    }

    /**
     * @use web端获取直播间信息
     * @param $room_id
     * @return array
     */
    public static function webGetRoomInfo($room_id): array
    {
        $url = 'https://api.live.bilibili.com/xlive/web-room/v1/index/getInfoByRoom';
        $payload = [
            'room_id' => $room_id
        ];
        $raw = Curl::get('other', $url, $payload);
        return json_decode($raw, true);
    }

    /**
     * @use 钓鱼检测
     * @param $room_id
     * @return bool
     */
    public static function fishingDetection($room_id): bool
    {
        if (self::getRealRoomID($room_id)) {
            return false;
        }
        return true;
    }


    /**
     * @use 访问直播间
     * @param $room_id
     * @return bool
     */
    public static function goToRoom($room_id): bool
    {
        $url = 'https://api.live.bilibili.com/room/v1/Room/room_entry_action';
        $payload = [
            'room_id' => $room_id,
        ];
        // Log::info('进入直播间[' . $room_id . ']抽奖!');
        Curl::post('app', $url, Sign::common($payload));
        return true;
    }


    /**
     * @use 获取毫秒
     * @return float
     */
    public static function getMillisecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }


    /**
     * @use 发送弹幕
     * @param int $room_id
     * @param string $content
     * @return array
     */
    public static function sendBarrage(int $room_id, string $content): array
    {
        $user_info = User::parseCookies();
        $url = 'https://api.live.bilibili.com/msg/send';
        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $content,
            'rnd' => 0,
            'bubble' => 0,
            'roomid' => $room_id,
            'csrf' => $user_info['token'],
            'csrf_token' => $user_info['token'],
        ];
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/{$room_id}"
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        return json_decode($raw, true) ?? ['code' => 404, 'msg' => '上层数据为空!'];
    }


    /**
     * @use 获取勋章列表
     * @param int $page_size
     * @return array
     */
    public static function fetchMedalList(int $page_size = 100): array
    {
        $metal_list = [];
        for ($i = 1; $i <= 10; $i++) {
            $url = 'https://api.live.bilibili.com/i/api/medal';
            $payload = [
                'page' => $i,
                'pageSize' => $page_size
            ];
            $raw = Curl::get('app', $url, Sign::common($payload));
            $de_raw = json_decode($raw, true);
            if (isset($data['code']) && $data['code']) {
                Log::warning('获取勋章列表失败!', ['msg' => $data['message']]);
                return $metal_list;
            }
            if (empty($de_raw['data']['fansMedalList'])) {
                return $metal_list;
            }
            if (isset($de_raw['data']['fansMedalList'])) {
                foreach ($de_raw['data']['fansMedalList'] as $vo) {
                    array_push($metal_list, $vo);
                }
            }
            if ($de_raw['data']['pageinfo']['totalpages'] == $de_raw['data']['pageinfo']['curPage']) {
                return $metal_list;
            }
        }
        Log::info('勋章列表获取成功!');
        return $metal_list;
    }

    /**
     * @use 背包获取单项礼物
     * @param string $gift_name
     * @param int $gift_id
     * @return array
     */
    public static function fetchBagListByGift(string $gift_name, int $gift_id): array
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
                if ($vo['corner_mark'] == '永久') continue;
                if ($vo['gift_id'] == $gift_id && $vo['gift_name'] == $gift_name){
                    array_push($new_bag_list, $vo);
                }
            }
        }
        return $new_bag_list;
    }

    /**
     * @use 赠送礼物
     * @param array $guest // 用户信息
     * @param array $gift // 礼物信息
     * @param int $num // 数量
     */
    public static function sendGift(array $guest, array $gift, int $num)
    {
        $url = 'https://api.live.bilibili.com/gift/v2/live/bag_send';
        $user_info = User::parseCookies();
        $payload = [
            'uid' => $user_info['uid'], // 自己的UID
            'gift_id' => $gift['gift_id'],
            'ruid' => $guest['uid'], // UP的UID
            'send_ruid' => 0,
            'gift_num' => $num,
            'bag_id' => $gift['bag_id'],
            'platform' => 'pc',
            'biz_code' => 'live',
            'biz_id' => $guest['roomid'], // UP的直播间
            'rnd' => time(), // 时间戳
            'storm_beat_id' => 0,
            'metadata' => '',
            'price' => 0,
            'csrf' => $user_info['token'],
            'csrf_token' => $user_info['token']
        ];
        // {"code":0,"msg":"success","message":"success","data":{"tid":"1595419985112400002","uid":4133274,"uname":"沙奈之朵","face":"https://i2.hdslb.com/bfs/face/eb101ef90ebc4e9bf79f65312a22ebac84946700.jpg","guard_level":0,"ruid":893213,"rcost":30834251,"gift_id":30607,"gift_type":5,"gift_name":"小心心","gift_num":1,"gift_action":"投喂","gift_price":5000,"coin_type":"silver","total_coin":5000,"pay_coin":5000,"metadata":"","fulltext":"","rnd":"1595419967","tag_image":"","effect_block":1,"extra":{"wallet":null,"gift_bag":{"bag_id":210196588,"gift_num":20},"top_list":[],"follow":null,"medal":null,"title":null,"pk":{"pk_gift_tips":"","crit_prob":0},"fulltext":"","event":{"event_score":0,"event_redbag_num":0},"capsule":null},"blow_switch":0,"send_tips":"赠送成功","gift_effect":{"super":0,"combo_timeout":0,"super_gift_num":0,"super_batch_gift_num":0,"batch_combo_id":"","broadcast_msg_list":[],"small_tv_list":[],"beat_storm":null,"smallTVCountFlag":true},"send_master":null,"crit_prob":0,"combo_stay_time":3,"combo_total_coin":0,"demarcation":2,"magnification":1,"combo_resources_id":1,"is_special_batch":0,"send_gift_countdown":6}}
        $data = Curl::post('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('送礼失败!', ['msg' => $data['message']]);
        } else {
            Log::notice("成功向 {$payload['biz_id']} 投喂了 {$num} 个{$gift['gift_name']}");
        }
    }
}
