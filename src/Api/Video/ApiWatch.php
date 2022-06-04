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

namespace Bhp\Api\Video;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiWatch
{
    /**
     * @use 观看视频
     * @param string $aid
     * @param string $cid
     * @return array
     */
    public static function video(string $aid, string $cid): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/click-interface/click/web/h5';
        $payload = [
            'aid' => $aid,
            'cid' => $cid,
            'part' => 1,
            'ftime' => time(),
            'jsonp' => 'jsonp',
            'mid' => $user['uid'],
            'csrf' => $user['csrf'],
            'stime' => time(),
            'lv' => '',
            'auto_continued_play' => 0,
            'refer_url' => "https://www.bilibili.com/video/av$aid"
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'Referer' => "https://www.bilibili.com/video/av$aid",
        ];
        // {"code":0,"message":"0","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * @use 发送心跳
     * @param string $aid
     * @param string $cid
     * @param int $duration
     * @param array|null $data
     * @return array
     */
    public static function heartbeat(string $aid, string $cid, int $duration, ?array $data = null): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/click-interface/web/heartbeat';
        $payload = [
            'aid' => $aid,
            'cid' => $cid,
            'mid' => $user['uid'],
            'csrf' => $user['csrf'],
            'jsonp' => 'jsonp',
            'played_time' => 0,
            'realtime' => $duration,
            'pause' => false,
            'play_type' => 1,
            'start_ts' => time()
        ];
        //
        if (!is_null($data)) {
            $payload = array_merge($payload, $data);
        }
        //
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'Referer' => "https://www.bilibili.com/video/av$aid",
        ];
        // {"code":0,"message":"0","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

}