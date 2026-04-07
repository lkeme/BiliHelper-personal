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

namespace Bhp\Api\Msg;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;
use Throwable;

class ApiMsg
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function sendBarragePC(int $room_id, string $content): array
    {
        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $content,
            'rnd' => 0,
            'bubble' => 0,
            'roomid' => $room_id,
            'csrf' => $this->request->csrfValue(),
            'csrf_token' => $this->request->csrfValue(),
        ];

        return $this->decodePost('pc', 'https://api.live.bilibili.com/msg/send', $payload, [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/{$room_id}",
        ], 'msg.barrage.pc');
    }

    /**
     * @return array<string, mixed>
     */
    public function sendBarrageAPP(int $room_id, string $content): array
    {
        $csrf = $this->request->csrfValue();
        $payload = [
            'bubble' => 0,
            'msg' => $content,
            'color' => 16777215,
            'mode' => 1,
            'room_type' => 0,
            'jumpfrom' => 0,
            'reply_mid' => 0,
            'reply_attr' => 0,
            'replay_dmid' => '',
            'statistics' => '{"appId":100,"platform":5}',
            'reply_type' => 0,
            'reply_uname' => '',
            'fontsize' => 25,
            'rnd' => time(),
            'roomid' => $room_id,
            'csrf' => $csrf,
            'csrf_token' => $csrf,
        ];

        return $this->decodePost(
            'pc',
            'https://api.live.bilibili.com/msg/send',
            $payload,
            [
                'origin' => 'https://live.bilibili.com',
                'referer' => "https://live.bilibili.com/{$room_id}",
            ],
            'msg.barrage.app',
            WbiSign::encryption([
                'web_location' => '444.8',
            ]),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function decodePost(string $os, string $url, array $payload, array $headers, string $label, array $query = []): array
    {
        try {
            $raw = $query === []
                ? $this->request->postText($os, $url, $payload, $headers)
                : $this->request->postTextWithQuery($os, $url, $payload, $query, $headers);
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
