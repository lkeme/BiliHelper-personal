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

class Live
{
    public static $lock = 0;

    // RUN
    public static function run()
    {
        // TODO
        self::isSleep();
    }

    // GET RANDOW ROOM_ID
    public static function getUserRecommend()
    {
        while (1) {
            $raw = Curl::get('https://api.live.bilibili.com/room/v3/area/getRoomList?sort_type=online&page_size=30&page=' . random_int(0, 5));
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] != '0') {
                continue;
            }
            break;
        }
        $rand_num = random_int(1, 29);

        return $de_raw['data']['list'][$rand_num]['roomid'];
    }

    // GET REALROOM_ID
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

    // 钓鱼检测
    public static function fishingDetection($room_id): bool
    {
        if (self::getRealRoomID($room_id)) {
            return false;
        }
        return true;
    }

    // RANDOM DELAY
    public static function randFloat($min = 0, $max = 3): bool
    {
        $rand = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        sleep($rand);
        return true;
    }

    //TO ROOM
    public static function goToRoom($room_id): bool
    {
        $user_info = User::parseCookies();
        $payload = [
            'room_id' => $room_id,
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => null,
        ];
        Curl::post('https://api.live.bilibili.com/room/v1/Room/room_entry_action', Sign::api($payload));
        // Log::info('进入直播间[' . $room_id . ']抽奖!');
        return true;
    }

    // get Millisecond
    public static function getMillisecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    // IS SLEEP
    public static function isSleep()
    {
        if (self::$lock > time()) {
            return;
        }
        self::$lock = time() + 5 * 60;

        $hour = date('H');
        if ($hour >= 2 && $hour < 6) {
            self::bannedVisit('sleep');
            Log::warning('休眠时间,暂停非必要任务,4小时后自动开启!');
            return;
        }

        $payload = [];
        $raw = Curl::get('https://api.live.bilibili.com/mobile/freeSilverAward', Sign::api($payload));
        $de_raw = json_decode($raw, true);
        if ($de_raw['msg'] == '访问被拒绝') {
            self::bannedVisit('ban');
            Log::warning('账号拒绝访问,暂停非必要任务,凌晨自动开启!');
        }
        return;
    }

    //被封禁访问
    public static function bannedVisit($arg)
    {
        //获取当前时间
        $block_time = strtotime(date("Y-m-d H:i:s"));

        if ($arg == 'ban') {
            $unblock_time = strtotime(date("Y-m-d", strtotime("+1 day", $block_time)));
        } elseif ($arg == 'sleep') {
            // TODO
            $unblock_time = $block_time + 4 * 60 * 60;
        } else {
            $unblock_time = time();
        }

        $second = time() + ceil($unblock_time - $block_time) + 5 * 60;
        $hour = floor(($second - time()) / 60 / 60);

        if ($arg == 'ban') {
            // 推送被ban信息
            Notice::run('banned', $hour);
        }

        self::$lock = $second;

        Silver::$lock = $second;
        MaterialObject::$lock = $second;
        GiftHeart::$lock = $second;
        TcpClient::$lock = $second;
        return;
    }

}