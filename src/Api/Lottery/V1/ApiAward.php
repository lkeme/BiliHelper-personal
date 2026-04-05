<?php declare(strict_types=1);

namespace Bhp\Api\Lottery\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiAward
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function awardList(): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.live.bilibili.com/lottery/v1/Award/award_list', [
                'page' => 1,
                'month' => '',
            ], [
                'origin' => 'https://link.bilibili.com',
                'referer' => 'https://link.bilibili.com/p/center/index',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'lottery.award.list 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'lottery.award.list');
    }
}
