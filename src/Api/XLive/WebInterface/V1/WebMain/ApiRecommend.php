<?php declare(strict_types=1);

namespace Bhp\Api\XLive\WebInterface\V1\WebMain;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiRecommend extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMoreRecList(): array
    {
        return $this->decodeGet('pc', 'https://api.live.bilibili.com/xlive/web-interface/v1/webMain/getMoreRecList', [
            'platform' => 'web',
            'web_location' => '333.1007',
        ], [], 'xlive.web_main.get_more_rec_list');
    }
}
