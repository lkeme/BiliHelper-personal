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

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiWatch
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * 观看视频
     * @param string $aid
     * @param string $cid
     * @param string $bvid
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>
     */
    public function video(string $aid, string $cid, string $bvid = '', ?array $data = null): array
    {
        $url = 'https://api.bilibili.com/x/click-interface/click/web/h5';
        $referer = $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}";
        $payload = [
            'aid' => $aid,
            'bvid' => $bvid,
            'cid' => $cid,
            'part' => 1,
            'ftime' => time(),
            'jsonp' => 'jsonp',
            'mid' => $this->request->uidValue(),
            'csrf' => $this->request->csrfValue(),
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
        if ($data !== null) {
            $payload = array_merge($payload, $data);
        }

        return $this->decodePost('pc', $url, $payload, [
            'origin' => 'https://www.bilibili.com',
            'Referer' => $referer,
        ], 'video.watch.start');
    }

    /**
     * 发送心跳
     * @param string $aid
     * @param string $cid
     * @param int $duration
     * @param string $bvid
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>
     */
    public function heartbeat(string $aid, string $cid, int $duration, string $bvid = '', ?array $data = null): array
    {
        $url = 'https://api.bilibili.com/x/click-interface/web/heartbeat';
        $referer = $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}";
        $payload = [
            'aid' => $aid,
            'bvid' => $bvid,
            'cid' => $cid,
            'mid' => $this->request->uidValue(),
            'csrf' => $this->request->csrfValue(),
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
        if ($data !== null) {
            $payload = array_merge($payload, $data);
        }

        return $this->decodePost('pc', $url, $payload, [
            'origin' => 'https://www.bilibili.com',
            'Referer' => $referer,
        ], 'video.watch.heartbeat');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodePost(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->postText($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, $label);
    }
}
