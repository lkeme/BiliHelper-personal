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
use JetBrains\PhpStorm\ArrayShape;

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
        $url = "https://api.live.bilibili.com/room/v1/Area/getList";
        $payload = [];
        $raw = Curl::get('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        // 防止异常
        if (!isset($de_raw['data']) || $de_raw['code']) {
            Log::warning("获取直播分区异常: " . $de_raw['msg']);
            $areas = range(1, 6);
        } else {
            foreach ($de_raw['data'] as $area) {
                $areas[] = $area['id'];
            }
        }
        return $areas;
    }

    /**
     * @use AREA_ID转ROOM_ID
     * @param $area_id
     * @return array
     */
    #[ArrayShape(['area_id' => "", 'room_id' => "int|mixed"])]
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
    public static function getUserRecommend(): int
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
//        print_r($de_raw);
        if ($de_raw['code'] != '0') {
            return 23058;
        }
        return $de_raw['data'][mt_rand(1, 29)]['roomid'];
    }

    /**
     * @use 获取直播房间号
     * @param $room_id
     * @param bool $uid
     * @return mixed
     */
    public static function getRealRoomID($room_id, bool $uid = false): mixed
    {
        $room_infos = [];
        // 缓存开始 如果存在就赋值 否则默认值
        if ($temp = getCache('room_infos')) {
            $room_infos = $temp;
        }
        // 取缓存
        if (isset($room_infos[strval($room_id)])) {
            $data = $room_infos[strval($room_id)];
        } else {
            // 默认数据
            $_data = ['uid' => false, 'room_id' => false];
            // TODO 优化
            $data = self::getRoomInfoV1($room_id);
            if (!isset($data['code']) || !isset($data['data'])) {
                // 访问错误
                $data = $_data;
            } elseif ($data['code']) {
                // 访问错误
                $data = $_data;
                Log::warning($room_id . ' : ' . $data['msg']);
            } elseif ($data['data']['is_hidden']) {
                // 隐藏
                $data = $_data;
            } elseif ($data['data']['is_locked']) {
                // 锁定
                $data = $_data;
            } elseif ($data['data']['encrypted']) {
                // 加密
                $data = $_data;
            } else {
                // 有效
                $data = [
                    'uid' => $data['data']['uid'],
                    'room_id' => $data['data']['room_id'],
                ];
            }
            // 推入缓存前
            $room_infos[strval($room_id)] = $data;
        }
        // 缓存结束 需要的数据的放进缓存
        setCache('room_infos', $room_infos);
        // 如果需要UID
        if ($uid) return $data;
        // 否
        return $data['room_id'];
    }

    /**
     * @use 获取直播间信息
     * @param $room_id
     * @return array
     */
    public static function getRoomInfoV1($room_id): array
    {
        $url = 'https://api.live.bilibili.com/room/v1/Room/room_init';
        $payload = [
            'id' => $room_id
        ];
        $raw = Curl::get('other', $url, $payload);
        return json_decode($raw, true);
    }

    /**
     * @use 获取直播间信息
     * @param $room_id
     * @return array
     */
    public static function getRoomInfoV2($room_id): array
    {
        $url = ' https://api.live.bilibili.com/room/v1/Room/get_info_by_id';
        $payload = [
            'ids[]' => $room_id
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
    #[ArrayShape(['addr' => "mixed|string", 'token' => "mixed|string"])]
    public static function getDanMuInfo($room_id): array
    {
        $data = self::getDanMuConf($room_id);
        if (isset($data['data']['host_server_list'][0]['host'])) {
            $server = $data['data']['host_server_list'][0];
            $addr = "tcp://{$server['host']}:{$server['port']}/sub";
        } else {
            $addr = getConf('server_addr', 'zone_monitor');
        }
        return [
            'addr' => $addr,
            'token' => $data['data']['token'] ?? '',
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
     * @use 发送弹幕pc
     * @param int $room_id
     * @param string $content
     * @return array
     */
    public static function sendBarragePC(int $room_id, string $content): array
    {
        $room_id = self::getRealRoomID($room_id);
        if (!$room_id) {
            return ['code' => 404, 'message' => '直播间数据异常'];
        }
        $url = 'https://api.live.bilibili.com/msg/send';
        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $content,
            'rnd' => 0,
            'bubble' => 0,
            'roomid' => $room_id,
            'csrf' => getCsrf(),
            'csrf_token' => getCsrf(),
        ];
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/$room_id"
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        // {"code":0,"data":[],"message":"","msg":""}
        return json_decode($raw, true) ?? ['code' => 404, 'msg' => '上层数据为空!'];
    }

    /**
     * @use 发送弹幕app
     * @param int $room_id
     * @param string $content
     * @return array
     */
    public static function sendBarrageAPP(int $room_id, string $content): array
    {
        $room_id = self::getRealRoomID($room_id);
        if (!$room_id) {
            return ['code' => 404, 'message' => '直播间数据异常'];
        }
        $url = 'https://api.live.bilibili.com/msg/send';
        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $content,
            'rnd' => 0,
            'roomid' => $room_id,
            'csrf' => getCsrf(),
            'csrf_token' => getCsrf(),
        ];
        $raw = Curl::post('app', $url, Sign::common($payload));
        return json_decode($raw, true) ?? ['code' => 404, 'msg' => '上层数据为空!'];
    }

    /**
     * @use 获取勋章列表
     * @param int $page_size
     * @return array
     */
    public static function fetchMedalList(int $page_size = 50): array
    {
        $metal_list = [];
        for ($i = 1; $i <= 100; $i++) {
            // https://live.bilibili.com/p/html/live-app-fansmedal-manange/index.html
            $url = 'https://api.live.bilibili.com/xlive/app-ucenter/v1/fansMedal/panel';
            $payload = [
                'page' => $i,
                'page_size' => $page_size
            ];
            $raw = Curl::get('app', $url, Sign::common($payload));
            $de_raw = json_decode($raw, true);
            // {"code":0,"message":"0","ttl":1,"data":{"list":[],"special_list":[],"bottom_bar":null,"page_info":{"number":0,"current_page":1,"has_more":false,"next_page":2,"next_light_status":2},"total_number":0,"has_medal":0}}
            if (isset($data['code']) && $data['code']) {
                Log::warning('获取勋章列表失败!', ['msg' => $data['message']]);
                return $metal_list;
            }
            // list special_list
            $keys = ['list', 'special_list'];
            foreach ($keys as $key) {
                if (isset($de_raw['data'][$key])) {
                    foreach ($de_raw['data'][$key] as $vo) {
                        // 部分主站勋章没有直播间
                        if (isset($vo['room_info']['room_id'])) {
                            $vo['medal']['roomid'] = $vo['room_info']['room_id'];
                        } else {
                            $vo['medal']['roomid'] = 0;
                        }
                        $metal_list[] = $vo['medal'];
                    }
                }
            }
            // total_number || count == 0
            if (count($metal_list) >= $de_raw['data']['total_number'] || empty($metal_list)) {
                break;
            }
        }
        // count == 0
        if (!empty($metal_list)) {
            $num = count($metal_list);
            Log::info("勋章列表获取成功, 共获取到 $num 个!");
        }
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
                if ($vo['gift_id'] == $gift_id && $vo['gift_name'] == $gift_name) {
                    $new_bag_list[] = $vo;
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
    public static function sendGift(array $guest, array $gift, int $num): void
    {
        $url = 'https://api.live.bilibili.com/gift/v2/live/bag_send';
        $payload = [
            'uid' => getUid(), // 自己的UID
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
            'csrf' => getCsrf(),
            'csrf_token' => getCsrf()
        ];
        // {"code":0,"msg":"success","message":"success","data":{"tid":"1595419985112400002","uid":4133274,"uname":"沙奈之朵","face":"https://i2.hdslb.com/bfs/face/eb101ef90ebc4e9bf79f65312a22ebac84946700.jpg","guard_level":0,"ruid":893213,"rcost":30834251,"gift_id":30607,"gift_type":5,"gift_name":"小心心","gift_num":1,"gift_action":"投喂","gift_price":5000,"coin_type":"silver","total_coin":5000,"pay_coin":5000,"metadata":"","fulltext":"","rnd":"1595419967","tag_image":"","effect_block":1,"extra":{"wallet":null,"gift_bag":{"bag_id":210196588,"gift_num":20},"top_list":[],"follow":null,"medal":null,"title":null,"pk":{"pk_gift_tips":"","crit_prob":0},"fulltext":"","event":{"event_score":0,"event_redbag_num":0},"capsule":null},"blow_switch":0,"send_tips":"赠送成功","gift_effect":{"super":0,"combo_timeout":0,"super_gift_num":0,"super_batch_gift_num":0,"batch_combo_id":"","broadcast_msg_list":[],"small_tv_list":[],"beat_storm":null,"smallTVCountFlag":true},"send_master":null,"crit_prob":0,"combo_stay_time":3,"combo_total_coin":0,"demarcation":2,"magnification":1,"combo_resources_id":1,"is_special_batch":0,"send_gift_countdown":6}}
        $data = Curl::post('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::warning('送礼失败!', ['msg' => $data['message']]);
        } else {
            Log::notice("成功向 {$payload['biz_id']} 投喂了 $num 个{$gift['gift_name']}");
        }
    }

    /**
     * @use 获取分区直播间
     * @param int $parent_area_id
     * @param int $area_id
     * @param int $page
     * @return array
     */
    public static function getAreaRoomList(int $parent_area_id, int $area_id, int $page = 1): array
    {
        $url = 'https://api.live.bilibili.com/xlive/web-interface/v1/second/getList';
        $payload = [
            'platform' => 'web',
            'parent_area_id' => $parent_area_id,
            'area_id' => $area_id,
            'sort_type' => 'online',
            'page' => $page
        ];
        $raw = Curl::get('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        $room_ids = [];

        if ($de_raw['code'] == 0) {
            foreach ($de_raw['data']['list'] as $room) {
                $room_ids[] = $room['roomid'];
            }
        }
        return $room_ids;
    }

    /**
     * @use 获取用户卡片
     * @param int $mid
     * @return array
     */
    public static function getMidCard(int $mid): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/card';
        $payload = [
            'mid' => $mid,
        ];
        //{"code":0,"message":"0","ttl":1,"data":{"card":{"mid":"1","name":"bishi","approve":false,"sex":"男","rank":"10000","face":"http://i1.hdslb.com/bfs/face/34c5b30a990c7ce4a809626d8153fa7895ec7b63.gif","DisplayRank":"0","regtime":0,"spacesta":0,"birthday":"","place":"","description":"","article":0,"attentions":[],"fans":154167,"friend":5,"attention":5,"sign":"","level_info":{"current_level":4,"current_min":0,"current_exp":0,"next_exp":0},"pendant":{"pid":0,"name":"","image":"","expire":0,"image_enhance":"","image_enhance_frame":""},"nameplate":{"nid":0,"name":"","image":"","image_small":"","level":"","condition":""},"Official":{"role":0,"title":"","desc":"","type":-1},"official_verify":{"type":-1,"desc":""},"vip":{"type":2,"status":1,"due_date":1727625600000,"vip_pay_type":1,"theme_type":0,"label":{"path":"","text":"年度大会员","label_theme":"annual_vip","text_color":"#FFFFFF","bg_style":1,"bg_color":"#FB7299","border_color":""},"avatar_subscript":1,"nickname_color":"#FB7299","role":3,"avatar_subscript_url":"http://i0.hdslb.com/bfs/vip/icon_Certification_big_member_22_3x.png","vipType":2,"vipStatus":1}},"following":false,"archive_count":2,"article_count":0,"follower":154167}}
        $raw = Curl::get('other', $url, $payload);
        return json_decode($raw, true);
    }

    /**
     * @use 获取用户状态
     * @param int $mid
     * @return array
     */
    public static function getMidStat(int $mid): array
    {
        $url = 'https://api.bilibili.com/x/relation/stat';
        $payload = [
            'vmid' => $mid,
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"mid":50329118,"following":62,"whisper":0,"black":0,"follower":7610241}}
        $raw = Curl::get('other', $url, $payload);
        return json_decode($raw, true);
    }

    /**
     * @use 直播间抽奖检查
     * @param int $room_id
     * @return array
     */
    public static function getLotteryCheck(int $room_id): array
    {
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/lottery/Check';
        $payload = [
            'roomid' => $room_id,
        ];
        $raw = Curl::get('other', $url, $payload);
        return json_decode($raw, true);
    }


    /**
     * @use 获取直播间抽奖信息
     * @param int $room_id
     * @return array
     */
    public static function getLotteryInfoWeb(int $room_id): array
    {
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/lottery/getLotteryInfoWeb';
        $payload = [
            'roomid' => $room_id,
        ];
        $raw = Curl::get('other', $url, $payload);
        return json_decode($raw, true);
    }


    /**
     * @use 获取用户关注数
     * @param int $mid
     * @return int
     */
    public static function getMidFollower(int $mid): int
    {
        $follower = 0;
        // root->data->follower
        if (mt_rand(0, 10) > 5) {
            $data = self::getMidStat($mid);
        } else {
            $data = self::getMidCard($mid);
        }

        if (isset($data['code']) && $data['code']) {
            Log::warning("获取用户资料卡片失败: CODE -> {$data['code']} MSG -> {$data['message']} ");
        } else {
            // root->data->follower
            $follower = $data['data']['follower'];
        }
        return $follower;
    }
}
