<?php declare(strict_types=1);

namespace Bhp\Api\XLive\WebRoom\V1\Index;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;
use Throwable;

class ApiIndex
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfoByRoom(int $roomId): array
    {
        try {
            $raw = $this->request->getText('other', 'https://api.live.bilibili.com/xlive/web-room/v1/index/getInfoByRoom', [
                'room_id' => $roomId,
                'web_location' => '444.8',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.web_room.get_info_by_room 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.web_room.get_info_by_room');
    }

    /**
     * @return array<string, mixed>
     */
    public function roomEntryAction(int $roomId): array
    {
        $query = WbiSign::encryption([
            'csrf' => $this->request->csrfValue(),
        ]);

        try {
            $raw = $this->request->postText('pc', 'https://api.live.bilibili.com/xlive/web-room/v1/index/roomEntryAction?' . http_build_query($query), [
                'room_id' => $roomId,
                'platform' => 'pc',
            ], [
                'origin' => 'https://live.bilibili.com',
                'referer' => "https://live.bilibili.com/{$roomId}",
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.web_room.room_entry_action 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.web_room.room_entry_action');
    }
}
