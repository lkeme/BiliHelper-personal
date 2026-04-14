<?php declare(strict_types=1);

namespace Bhp\Api\Room\V1;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiInfo extends AbstractApiClient
{
    /**
     * 初始化 ApiInfo
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRoomInfoV1(int|string $roomId): array
    {
        return $this->decodeGet('other', 'https://api.live.bilibili.com/room/v1/Room/room_init', [
            'id' => $roomId,
        ], [], 'room.info.v1');
    }

    /**
     * @return array<string, mixed>
     */
    public function getRoomInfoV2(int|string $roomId): array
    {
        return $this->decodeGet('other', 'https://api.live.bilibili.com/room/v1/Room/get_info_by_id', [
            'ids[]' => $roomId,
        ], [], 'room.info.v2');
    }
}
