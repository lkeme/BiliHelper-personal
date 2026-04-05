<?php declare(strict_types=1);

namespace Bhp\Api\XLive\GeneralInterface\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiGuardBenefit
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function winListByUser(): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.live.bilibili.com/xlive/general-interface/v1/guardBenefit/WinListByUser', [
                'page' => 1,
            ], [
                'origin' => 'https://link.bilibili.com',
                'referer' => 'https://link.bilibili.com/p/center/index',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.guard_benefit.win_list_by_user 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.guard_benefit.win_list_by_user');
    }
}
