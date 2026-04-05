<?php declare(strict_types=1);

namespace Bhp\Api\Room\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiDanMu
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRoomConfig(int $roomId): array
    {
        try {
            $raw = $this->request->getText('other', 'https://api.live.bilibili.com/room/v1/Danmu/getConf', [
                'room_id' => $roomId,
                'platform' => 'pc',
                'player' => 'web',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'room.danmu.get_conf 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'room.danmu.get_conf');
    }
}
