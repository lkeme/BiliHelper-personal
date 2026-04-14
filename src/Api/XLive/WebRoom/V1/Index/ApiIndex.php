<?php declare(strict_types=1);

namespace Bhp\Api\XLive\WebRoom\V1\Index;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;

class ApiIndex extends AbstractApiClient
{
    /**
     * 初始化 ApiIndex
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
    public function getInfoByRoom(int $roomId): array
    {
        return $this->decodeGet('other', 'https://api.live.bilibili.com/xlive/web-room/v1/index/getInfoByRoom', [
            'room_id' => $roomId,
            'web_location' => '444.8',
        ], [], 'xlive.web_room.get_info_by_room');
    }

    /**
     * @return array<string, mixed>
     */
    public function roomEntryAction(int $roomId): array
    {
        $query = WbiSign::encryption([
            'csrf' => $this->request()->csrfValue(),
        ]);

        return $this->decodePost('pc', 'https://api.live.bilibili.com/xlive/web-room/v1/index/roomEntryAction?' . http_build_query($query), [
            'room_id' => $roomId,
            'platform' => 'pc',
        ], [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/{$roomId}",
        ], 'xlive.web_room.room_entry_action');
    }
}
