<?php declare(strict_types=1);

namespace Bhp\Api\Room\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiInfo
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRoomInfoV1(int|string $roomId): array
    {
        try {
            $raw = $this->request->getText('other', 'https://api.live.bilibili.com/room/v1/Room/room_init', [
                'id' => $roomId,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'room.info.v1 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'room.info.v1');
    }

    /**
     * @return array<string, mixed>
     */
    public function getRoomInfoV2(int|string $roomId): array
    {
        try {
            $raw = $this->request->getText('other', 'https://api.live.bilibili.com/room/v1/Room/get_info_by_id', [
                'ids[]' => $roomId,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'room.info.v2 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'room.info.v2');
    }
}
