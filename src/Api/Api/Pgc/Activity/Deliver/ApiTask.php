<?php declare(strict_types=1);

namespace Bhp\Api\Api\Pgc\Activity\Deliver;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiTask extends AbstractApiClient
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task',
    ];

    /**
     * 初始化 ApiTask
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
    public function complete(string $position): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/deliver/task/complete', $this->request()->signCommonPayload([
            'disable_rcmd' => '0',
            'position' => $position,
            'csrf' => $this->request()->csrfValue(),
        ], true), self::HEADERS, 'pgc.deliver.complete');
    }

    /**
     * @return array<string, mixed>
     */
    public function materialReceive(string $epId, string $seasonId): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/deliver/material/receive', $this->request()->signCommonPayload([
            'csrf' => $this->request()->csrfValue(),
            'spmid' => 'united.player-video-detail.0.0',
            'season_id' => $seasonId,
            'activity_code' => '',
            'ep_id' => $epId,
            'from_spmid' => 'search.search-result.0.0',
        ], true), self::HEADERS, 'pgc.deliver.material.receive');
    }

    /**
     * @return array<string, mixed>
     */
    public function completeWatch(string $taskId, string $token, string $taskSign, string $timestamp): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/deliver/task/complete', $this->request()->signCommonPayload([
            'disable_rcmd' => '0',
            'task_id' => $taskId,
            'token' => $token,
            'task_sign' => $taskSign,
            'timestamp' => $timestamp,
            'csrf' => $this->request()->csrfValue(),
        ], true), self::HEADERS, 'pgc.deliver.complete.watch');
    }
}
