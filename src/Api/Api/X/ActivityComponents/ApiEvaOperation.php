<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\ActivityComponents;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiEvaOperation extends AbstractApiClient
{
    /**
     * 初始化 ApiEvaOperation
     * @param Request $request
     */
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * 活动运营位列表
     *
     * 单条 data.list 项的关键字段：
     *   object.live.room_id / live_status / uid / popularity_count
     *   object.account.mid / name / is_follow
     *
     * @param string $sourceId
     * @param int $ps
     * @param int $pn
     * @param string $referer
     * @return array<string, mixed>
     */
    public function list(string $sourceId, int $ps = 50, int $pn = 1, string $referer = 'https://www.bilibili.com/'): array
    {
        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            return [
                'code' => -400,
                'message' => 'source_id 不能为空',
                'data' => [],
            ];
        }

        return $this->decodeGet('pc', 'https://api.bilibili.com/x/activity_components/eva_operation/list', [
            'source_id' => $sourceId,
            'ps' => max(1, $ps),
            'pn' => max(1, $pn),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => $referer,
        ], 'activity_components.eva_operation.list');
    }
}
