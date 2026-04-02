<?php declare(strict_types=1);

namespace Bhp\Api\XLive\WebInterface\V1\Second;

use Bhp\Api\Support\ApiJson;
use Bhp\WbiSign\WbiSign;

final class ApiList
{
    /**
     * @return array<string, mixed>
     */
    public static function getList(
        int $parentAreaId,
        int $areaId = 0,
        int $page = 1,
        string $sortType = '',
        string $webId = '',
    ): array {
        $url = 'https://api.live.bilibili.com/xlive/web-interface/v1/second/getList';
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => sprintf(
                'https://live.bilibili.com/p/eden/area-tags?areaId=%d&parentAreaId=%d',
                $areaId > 0 ? $areaId : $parentAreaId,
                $parentAreaId
            ),
        ];
        $payload = [
            'platform' => 'web',
            'parent_area_id' => $parentAreaId,
            'area_id' => $areaId,
            'sort_type' => $sortType,
            'page' => max(1, $page),
            'web_location' => '444.253',
        ];
        if ($webId !== '') {
            $payload['w_webid'] = $webId;
        }

        return ApiJson::get('pc', $url, WbiSign::encryption($payload), $headers, 'xlive.second.get_list');
    }
}
