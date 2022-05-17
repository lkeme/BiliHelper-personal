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

class LiveReservation
{
    use TimeLock;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('live_reservation')) {
            return;
        }
        if (getConf('vmids', 'live_reservation') == "") {
            return;
        }
        $vmids = explode(',', getConf('vmids', 'live_reservation'));
        // 获取目标列表->获取预约列表->执行预约列表
        foreach ($vmids as $vmid) {
            $reservation_list = self::fetchReservation($vmid);
            foreach ($reservation_list as $reservation) {
                self::reserve($reservation);
            }
        }
        // 1-3小时
        self::setLock(mt_rand(1, 3) * 60 * 60);
    }

    /**
     * @use 尝试预约并抽奖
     * @param array $data
     */
    private static function reserve(array $data): void
    {
        $url = 'https://api.bilibili.com/x/space/reserve';
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded',
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$data['vmid']}"
        ];
        $payload = [
            'sid' => $data['sid'],
            'jsonp' => 'jsonp',
            'csrf' => getCsrf()
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1}
        Log::info($data['name'] . '|' . $data['vmid'] . '|' . $data['sid']);
        Log::info($data['text']);
        Log::info($data['jump_url']);

        if (!$de_raw['code']) {
            Log::notice("尝试预约并抽奖成功: {$de_raw['message']}");
        } else {
            Log::warning("尝试预约并抽奖失败: $raw");
        }

    }


    /**
     * @use 获取预约列表
     * @param string $vmid
     * @return array
     */
    private static function fetchReservation(string $vmid): array
    {
        $reservation_list = [];

        $url = 'https://api.bilibili.com/x/space/reservation';
        $payload = [
            'vmid' => $vmid,
        ];
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/$vmid/"
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1,"data":[{"sid":253672,"name":"直播预约：创世之音-虚拟偶像演唱会","total":6382,"stime":1636716437,"etime":1637408100,"is_follow":1,"state":100,"oid":"","type":2,"up_mid":9617619,"reserve_record_ctime":1636731801,"live_plan_start_time":1637406000,"lottery_type":1,"lottery_prize_info":{"text":"预约有奖：小电视年糕抱枕、哔哩哔哩小电视樱花毛绒抱枕大号、哔哩哔哩小夜灯","lottery_icon":"https://i0.hdslb.com/bfs/activity-plat/static/ce06d65bc0a8d8aa2a463747ce2a4752/rgHplMQyiX.png","jump_url":"https://www.bilibili.com/h5/lottery/result?business_id=253672\u0026business_type=10\u0026lottery_id=76240"},"show_total":true,"subtitle":""},{"sid":246469,"name":"直播预约：创世之音-YuNi个人演唱会","total":3555,"stime":1636367836,"etime":1637494500,"is_follow":0,"state":100,"oid":"","type":2,"up_mid":9617619,"reserve_record_ctime":0,"live_plan_start_time":1637492400,"show_total":true,"subtitle":""}]}
        if (!$de_raw['code']) {
            // data == NULL
            $de_data = $de_raw['data'] ?: [];
            foreach ($de_data as $data) {
                $result = self::checkLottery($data);
                if (!$result) continue;
                $reservation_list[] = $result;
            }
        } else {
            Log::warning("获取预约列表失败: $raw");
        }
        return $reservation_list;
    }


    /**
     * @use 检测有效抽奖
     * @param array $data
     * @return bool|array
     */
    private static function checkLottery(array $data): bool|array
    {
        // 已经过了有效时间
        if ($data['etime'] <= time()) {
            return false;
        }
        // 已经预约过了
        if ($data['is_follow']) {
            return false;
        }
        // 有预约抽奖
        if (array_key_exists('lottery_prize_info', $data) && array_key_exists('lottery_type', $data)) {
            return [
                'sid' => $data['sid'], // 246469
                'name' => $data['name'], // "直播预约：创世之音-虚拟偶像演唱会"
                'vmid' => $data['up_mid'], // 9617619
                'jump_url' => $data['lottery_prize_info']['jump_url'], // "https://www.bilibili.com/h5/lottery/result?business_id=253672&business_type=10&lottery_id=76240"
                'text' => $data['lottery_prize_info']['text'], // "预约有奖：小电视年糕抱枕、哔哩哔哩小电视樱花毛绒抱枕大号、哔哩哔哩小夜灯"
            ];
        }
        return false;
    }
}