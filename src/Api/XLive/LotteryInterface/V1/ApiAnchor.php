<?php declare(strict_types=1);

namespace Bhp\Api\XLive\LotteryInterface\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiAnchor
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function awardRecord(): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.live.bilibili.com/xlive/lottery-interface/v1/Anchor/AwardRecord', [
                'page' => 1,
            ], [
                'origin' => 'https://link.bilibili.com',
                'referer' => 'https://link.bilibili.com/p/center/index',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.anchor.award_record 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.anchor.award_record');
    }
}
