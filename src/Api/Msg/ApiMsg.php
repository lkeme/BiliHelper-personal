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
        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $content,
            'rnd' => 0,
            'roomid' => $room_id,
            'csrf' => $this->request->csrfValue(),
            'csrf_token' => $this->request->csrfValue(),
        ];

        return $this->decodePost('app', 'https://api.live.bilibili.com/msg/send', $this->request->signCommonPayload($payload), [], 'msg.barrage.app');
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
