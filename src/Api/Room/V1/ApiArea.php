<?php declare(strict_types=1);

namespace Bhp\Api\Room\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiArea
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getList(): array
    {
        try {
            $raw = $this->request->getText('other', 'https://api.live.bilibili.com/room/v1/Area/getList');
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'room.area.get_list 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'room.area.get_list');
    }
}
