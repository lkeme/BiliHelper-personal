<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Api\X\Activity;

use Bhp\Api\Support\ApiJson;
use Bhp\User\User;

class ApiActivity
{
    /**
     * @param array $info
     * @param int $num
     * @return array
     */
    public static function doLottery(array $info, int $num = 1): array
    {
        $user = User::parseCookie();
        $url = 'https://api.bilibili.com/x/lottery/x/do';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $info['url'],
        ];
        $payload = [
            'sid' => $info['sid'],
            'num' => $num,
            'page_id' => (string)($info['page_id'] ?? ''),
            'csrf' => $user['csrf'],
        ];
        if (trim((string)($info['gaia_vtoken'] ?? '')) !== '') {
            $payload['gaia_vtoken'] = trim((string)$info['gaia_vtoken']);
        }
        // {"code":0,"message":"0","ttl":1,"data":[{"mid":6580464,"award_sid":"","type":2,"ctime":1775295108,"award_info":null,"order_no":"0@6580464_1775295108","state":0}]}
        return ApiJson::post('pc', $url, $payload, $headers, 'x.lottery.x.do');
    }

    /**
     * @param array $info
     * @param int $action_type 4 关注  3 分享
     * @return array
     */
    public static function addTimes(array $info, int $action_type = 3): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/lottery/addtimes';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $info['url'],
        ];
        $payload = [
            'sid' => $info['sid'],
            'action_type' => $action_type,
            'csrf' => $user['csrf'],
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"add_num":1}}
        return \Bhp\Api\Support\ApiJson::post( 'pc', $url, $payload, $headers);
    }

    /**
     * @param array $info
     * @return array
     */
    public static function myTimes(array $info): array
    {
        $url = 'https://api.bilibili.com/x/lottery/x/mytimes';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $info['url'],
        ];
        $payload = [
            'sid' => $info['sid'],
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"times":1,"lottery_type":0,"points":0,"points_per_time":0,"intergral":null}}
        return ApiJson::get('pc', $url, $payload, $headers, 'x.lottery.x.mytimes');
    }

    /**
     * 现代 ERA 任务页点击类完成动作
     * @return array<string, mixed>
     */
    public static function sendPoints(string $taskId, string $counter, string $referer = 'https://www.bilibili.com/'): array
    {
        $user = User::parseCookie();
        $url = 'https://api.bilibili.com/x/activity/task/send_points';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $referer,
        ];
        $payload = [
            'activity' => $taskId,
            'business' => $counter,
            'timestamp' => (int)round(microtime(true) * 1000),
            'csrf' => $user['csrf'],
        ];

        return ApiJson::post('pc', $url, $payload, $headers, 'x.activity.send_points');
    }

}
