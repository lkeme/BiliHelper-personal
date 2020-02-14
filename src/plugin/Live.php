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


    public static function run()
    {
        return;
    }


    /**
     * @use 获取分区列表
     * @return array
     */
    public static function fetchLiveAreas(): array
    {
        $areas = [];
        $url = "http://api.live.bilibili.com/room/v1/Area/getList";
        $raw = Curl::get($url);
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
        $url = "https://api.live.bilibili.com/room/v1/area/getRoomList?platform=web&parent_area_id={$area_id}&cate_id=0&area_id=0&sort_type=online&page=1&page_size=30";
        $raw = Curl::get($url);
        $de_raw = json_decode($raw, true);
        // 防止异常
        if (!isset($de_raw['data']) || $de_raw['code']) {
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
     * @throws \Exception
     */
    public static function getUserRecommend()
    {
        $raw = Curl::get('https://api.live.bilibili.com/room/v1/Area/getListByAreaID?areaId=0&sort=online&pageSize=30&page=1');
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] != '0') {
            return 23058;
        }
        return $de_raw['data'][random_int(1, 29)]['roomid'];
    }


    /**
     * @use 获取直播房间号
     * @param $room_id
     * @return bool
     */
    public static function getRealRoomID($room_id)
    {
        $raw = Curl::get('https://api.live.bilibili.com/room/v1/Room/room_init?id=' . $room_id);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code']) {
            Log::warning($room_id . ' : ' . $de_raw['msg']);
            return false;
        }
        if ($de_raw['data']['is_hidden']) {
            return false;
        }
        if ($de_raw['data']['is_locked']) {
            return false;
        }
        if ($de_raw['data']['encrypted']) {
            return false;
        }
        return $de_raw['data']['room_id'];

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
     * @use 随机延迟
     * @param int $min
     * @param int $max
     * @return bool
     */
    public static function randDelay($min = 0, $max = 3): bool
    {
        $rand = $min + random_int() / mt_getrandmax() * ($max - $min);
        sleep($rand);
        return true;
    }

    /**
     * @use 访问直播间
     * @param $room_id
     * @return bool
     */
    public static function goToRoom($room_id): bool
    {
        $payload = [
            'room_id' => $room_id,
        ];
        // Log::info('进入直播间[' . $room_id . ']抽奖!');
        Curl::post('https://api.live.bilibili.com/room/v1/Room/room_entry_action', Sign::api($payload));
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