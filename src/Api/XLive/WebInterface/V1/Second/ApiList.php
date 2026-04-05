<?php declare(strict_types=1);

namespace Bhp\Api\XLive\WebInterface\V1\Second;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;
use Throwable;

final class ApiList
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getList(int $parentAreaId, int $areaId = 0, int $page = 1, string $sortType = '', string $webId = ''): array
    {
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

        try {
            $raw = $this->request->getText('pc', 'https://api.live.bilibili.com/xlive/web-interface/v1/second/getList', WbiSign::encryption($payload), [
                'origin' => 'https://live.bilibili.com',
                'referer' => sprintf(
                    'https://live.bilibili.com/p/eden/area-tags?areaId=%d&parentAreaId=%d',
                    $areaId > 0 ? $areaId : $parentAreaId,
                    $parentAreaId,
                ),
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'xlive.second.get_list 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'xlive.second.get_list');
    }
}
