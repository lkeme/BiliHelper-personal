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
        $url = 'https://api.live.bilibili.com/xlive/web-room/v1/index/getInfoByRoom?=23058';
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

}