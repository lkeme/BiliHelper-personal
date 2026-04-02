<?php declare(strict_types=1);

namespace Bhp\Api\XLive\WebInterface\V1\WebMain;

use Bhp\Api\Support\ApiJson;

final class ApiRecommend
{
    /**
     * @return array<string, mixed>
     */
    public static function getMoreRecList(): array
    {
        return ApiJson::get(
            'pc',
            'https://api.live.bilibili.com/xlive/web-interface/v1/webMain/getMoreRecList',
            [
                'platform' => 'web',
                'web_location' => '333.1007',
            ],
            [],
            'xlive.web_main.get_more_rec_list'
        );
    }
}
