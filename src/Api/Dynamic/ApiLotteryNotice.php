<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiLotteryNotice extends AbstractApiClient
{
    /**
     * 初始化 ApiLotteryNotice
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
    public function notice(int $dynamicId): array
    {
        return $this->decodeGet('pc', 'https://api.vc.bilibili.com/lottery_svr/v1/lottery_svr/lottery_notice', [
            'business_id' => $dynamicId,
            'business_type' => 1,
            'csrf' => $this->request()->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'dynamic.lottery_notice');
    }
}
