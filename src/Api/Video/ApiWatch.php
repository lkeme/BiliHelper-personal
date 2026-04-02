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

namespace Bhp\Api\Video;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiWatch
{
    /**
     * 观看视频
     * @param string $aid
     * @param string $cid
     * @param string $bvid
     * @param array<string, mixed>|null $data
     * @return array
     */
    public static function video(string $aid, string $cid, string $bvid = '', ?array $data = null): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/click-interface/click/web/h5';
        $referer = $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}";
        $payload = [
            'aid' => $aid,
            'bvid' => $bvid,
            'cid' => $cid,
            'part' => 1,
            'ftime' => time(),
            'jsonp' => 'jsonp',
            'mid' => $user['uid'],
            'csrf' => $user['csrf'],
            'stime' => time(),
            'lv' => '',
            'auto_continued_play' => 0,
            'referer_url' => $referer,
            'type' => 3,
            'sub_type' => 0,
            'outer' => 0,
            'spmid' => '333.788.0.0',
            'from_spmid' => '333.999.0.0',
        ];
        if (!is_null($data)) {
            $payload = array_merge($payload, $data);
        }
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'Referer' => $referer,
        ];
        // {"code":0,"message":"0","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 发送心跳
     * @param string $aid
     * @param string $cid
     * @param int $duration
     * @param string $bvid
     * @param array|null $data
     * @return array
     */
    public static function heartbeat(string $aid, string $cid, int $duration, string $bvid = '', ?array $data = null): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/click-interface/web/heartbeat';
        $referer = $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}";
        $payload = [
            'aid' => $aid,
            'bvid' => $bvid,
            'cid' => $cid,
            'mid' => $user['uid'],
            'csrf' => $user['csrf'],
            'jsonp' => 'jsonp',
            'played_time' => 0,
            'realtime' => $duration,
            'real_played_time' => $duration,
            'video_duration' => $duration,
            'refer_url' => $referer,
            'pause' => false,
            'play_type' => 1,
            'start_ts' => time(),
            'type' => 3,
            'sub_type' => 0,
            'outer' => 0,
            'dt' => 2,
            'spmid' => '333.788.0.0',
            'from_spmid' => '333.999.0.0',
        ];
        //
        if (!is_null($data)) {
            $payload = array_merge($payload, $data);
        }
        //
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'Referer' => $referer,
        ];
        // {"code":0,"message":"0","ttl":1}
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

}
