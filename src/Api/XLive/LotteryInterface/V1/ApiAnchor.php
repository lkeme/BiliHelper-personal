<?php declare(strict_types=1);

namespace Bhp\Api\XLive\LotteryInterface\V1;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiAnchor extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function awardRecord(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/xlive/lottery-interface/v1/Anchor/AwardRecord', [
            'page' => 1,
        ], [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index',
        ], 'xlive.anchor.award_record');
    }
}
