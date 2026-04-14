<?php declare(strict_types=1);

namespace Bhp\Api\Room\V1;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiDanMu extends AbstractApiClient
{
    /**
     * 初始化 ApiDanMu
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
    public function getRoomConfig(int $roomId): array
    {
        return $this->decodeGet('other', 'https://api.live.bilibili.com/room/v1/Danmu/getConf', [
            'room_id' => $roomId,
            'platform' => 'pc',
            'player' => 'web',
        ], [], 'room.danmu.get_conf');
    }
}
