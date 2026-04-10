<?php declare(strict_types=1);

namespace Bhp\Api\XLive\GeneralInterface\V1;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiGuardBenefit extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function winListByUser(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/xlive/general-interface/v1/guardBenefit/WinListByUser', [
            'page' => 1,
        ], [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index',
        ], 'xlive.guard_benefit.win_list_by_user');
    }
}
