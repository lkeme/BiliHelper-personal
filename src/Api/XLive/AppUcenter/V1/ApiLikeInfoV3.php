<?php declare(strict_types=1);

namespace Bhp\Api\XLive\AppUcenter\V1;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;

final class ApiLikeInfoV3 extends AbstractApiClient
{
    private const LIKE_REPORT_URL = 'https://api.live.bilibili.com/xlive/app-ucenter/v1/like_info_v3/like/likeReportV3';

    /**
     * 初始化 ApiLikeInfoV3
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
    public function likeReportV3(int $roomId, int $anchorId, int $clickTime): array
    {
        $roomId = max(1, $roomId);
        $anchorId = max(1, $anchorId);
        $clickTime = max(1, $clickTime);
        $csrf = $this->request()->csrfValue();

        return $this->decodePostWithQuery(
            'pc',
            self::LIKE_REPORT_URL,
            [],
            WbiSign::encryption([
                'click_time' => $clickTime,
                'room_id' => $roomId,
                'uid' => $this->request()->uidValue(),
                'anchor_id' => $anchorId,
                'web_location' => '444.8',
                'csrf' => $csrf,
            ]),
            [
                'origin' => 'https://live.bilibili.com',
                'referer' => "https://live.bilibili.com/{$roomId}",
            ],
            'xlive.like_info_v3.like_report_v3',
        );
    }
}
