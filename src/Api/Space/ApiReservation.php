<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Space;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiReservation
{
    /**
     * @use 获取预约列表
     * @param string $vmid
     * @return array
     */
    public static function reservation(string $vmid): array
    {
        $url = 'https://api.bilibili.com/x/space/reservation';
        $payload = [
            'vmid' => $vmid,
        ];
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/$vmid/"
        ];
        // {"code":0,"message":"0","ttl":1,"data":[{"sid":253672,"name":"直播预约：创世之音-虚拟偶像演唱会","total":6382,"stime":1636716437,"etime":1637408100,"is_follow":1,"state":100,"oid":"","type":2,"up_mid":9617619,"reserve_record_ctime":1636731801,"live_plan_start_time":1637406000,"lottery_type":1,"lottery_prize_info":{"text":"预约有奖：小电视年糕抱枕、哔哩哔哩小电视樱花毛绒抱枕大号、哔哩哔哩小夜灯","lottery_icon":"https://i0.hdslb.com/bfs/activity-plat/static/ce06d65bc0a8d8aa2a463747ce2a4752/rgHplMQyiX.png","jump_url":"https://www.bilibili.com/h5/lottery/result?business_id=253672\u0026business_type=10\u0026lottery_id=76240"},"show_total":true,"subtitle":""},{"sid":246469,"name":"直播预约：创世之音-YuNi个人演唱会","total":3555,"stime":1636367836,"etime":1637494500,"is_follow":0,"state":100,"oid":"","type":2,"up_mid":9617619,"reserve_record_ctime":0,"live_plan_start_time":1637492400,"show_total":true,"subtitle":""}]}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * @use 预约
     * @param int $sid
     * @param int $vmid
     * @return array
     */
    public static function reserve(int $sid, int $vmid): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/space/reserve';
        $payload = [
            'sid' => $sid,
            'jsonp' => 'jsonp',
            'csrf' => $user['csrf']
        ];
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded',
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/$vmid/"
        ];
        // {"code":0,"message":"0","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }


}