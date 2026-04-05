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

class ApiCoin
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * 查询稿件投币数
     * @return array<string, mixed>
     */
    public function coins(string $aid = '', string $bvid = ''): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/archive/coins';
        $payload = [
            'aid' => $aid,
            'bvid' => $bvid,
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}",
        ];

        return $this->decodeGet('pc', $url, $payload, $headers, 'video.coins');
    }

    /**
     * 投币Web
     * @param string $aid
     * @param int $multiply
     * @param int $select_like
     * @return array
     */
    public function webCoin(string $aid, int $multiply = 1, int $select_like = 0, string $bvid = ''): array
    {
        //
        $url = 'https://api.bilibili.com/x/web-interface/coin/add';
        //
        $payload = [
            'aid' => $aid,
            'bvid' => $bvid,
            'multiply' => $multiply, // 投币*1
            'select_like' => $select_like,// 默认不点赞
            'cross_domain' => 'true',
            'csrf' => $this->request->csrfValue(),
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'Referer' => $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av$aid",
        ];
        // {"code":34005,"message":"超过投币上限啦~","ttl":1,"data":{"like":false}}
        // {"code":0,"message":"0","ttl":1,"data":{"like":false}}
        // CODE -> 137001 MSG -> 账号封禁中，无法完成操作
        // CODE -> -650 MSG -> 用户等级太低
        // {"code":-401,"message":"非法访问","ttl":1,"data":{"ga_data":{"decisions":["verify_captcha_level3"],"risk_level":1,"grisk_id":"********","decision_ctx":{"buvid":"********infoc","decision_type":"4","hrid":"53903","ip":"********","mid":"********","origin_scene":"video_coin","referer":"https://www.bilibili.com/video/********","scene":"video_coin","ua":"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0","v_voucher":"voucher_e********b"}}}}
        return $this->decodePost('pc', $url, $payload, $headers, 'video.coin.web');
    }


    /**
     * 投币App
     * @param string $aid
     * @param int $multiply
     * @param int $select_like
     * @return array
     */
    public function appCoin(string $aid, int $multiply = 1, int $select_like = 0): array
    {
        $url = 'https://app.bilibili.com/x/v2/view/coin/add';
        //
        $payload = [
            'aid' => $aid,
            'multiply' => $multiply, // 投币*1
            'select_like' => $select_like,// 默认不点赞
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"prompt":true,"like":false,"guide":{"type":"share","title":"喜欢就分享给朋友吧"}}} [] []
        return $this->decodePost('app', $url, $this->request->signCommonPayload($payload), [], 'video.coin.app');
    }


    /**
     * 获取硬币
     * @return array
     */
    public function getCoin(): array
    {
        $url = 'https://account.bilibili.com/site/getCoin';
        $payload = [];
        $headers = [
            'referer' => 'https://account.bilibili.com/account/coin',
        ];
        // {"code":0,"status":true,"data":{"money":1707.9}}
        return $this->decodeGet('pc', $url, $payload, $headers, 'video.coin.get');
    }

    /**
     * 投币日志
     * @return array
     */
    public function addLog(): array
    {
        $url = 'https://api.bilibili.com/x/member/web/coin/log';
        $payload = [];
        return $this->decodeGet('pc', $url, $payload, [], 'video.coin.log');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodeGet(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->getText($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, $label);
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
