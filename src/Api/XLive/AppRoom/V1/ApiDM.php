<?php declare(strict_types=1);

namespace Bhp\Api\XLive\AppRoom\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiDM
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMsg(int $roomId, string $msg): array
    {
        $query = http_build_query($this->request->signCommonPayload([
            'aid' => '',
            'page' => '1',
        ]));

        return $this->decodePost('app', 'https://api.live.bilibili.com/xlive/app-room/v1/dM/sendmsg?' . $query, [
            'cid' => $roomId,
            'msg' => $msg,
            'rnd' => time(),
            'color' => '16777215',
            'fontsize' => '25',
        ], [
            'content-type' => 'application/x-www-form-urlencoded',
        ], 'xlive.app_room.send_msg');
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
