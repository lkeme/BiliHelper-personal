<?php declare(strict_types=1);

namespace Bhp\Api\XLive\LotteryInterface\V2;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiBox
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function draw(int $aid, int $round, int $roomId): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.live.bilibili.com/xlive/lottery-interface/v2/Box/draw', [
                'aid' => $aid,
                'number' => $round,
                'room_id' => $roomId,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.box.draw 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.box.draw');
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(int $aid): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.live.bilibili.com/xlive/lottery-interface/v2/Box/getStatus', [
                'aid' => $aid,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.box.get_status 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.box.get_status');
    }
}
