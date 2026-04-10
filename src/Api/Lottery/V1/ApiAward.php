<?php declare(strict_types=1);

namespace Bhp\Api\Lottery\V1;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiAward extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function awardList(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/lottery/v1/Award/award_list', [
            'page' => 1,
            'month' => '',
        ], [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index',
        ], 'lottery.award.list');
    }
}
