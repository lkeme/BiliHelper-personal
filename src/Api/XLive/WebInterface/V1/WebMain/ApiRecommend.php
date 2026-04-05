<?php declare(strict_types=1);

namespace Bhp\Api\XLive\WebInterface\V1\WebMain;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

final class ApiRecommend
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getMoreRecList(): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.live.bilibili.com/xlive/web-interface/v1/webMain/getMoreRecList', [
                'platform' => 'web',
                'web_location' => '333.1007',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.web_main.get_more_rec_list 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.web_main.get_more_rec_list');
    }
}
