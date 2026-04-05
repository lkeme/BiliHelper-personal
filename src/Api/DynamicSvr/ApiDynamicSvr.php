<?php declare(strict_types=1);

namespace Bhp\Api\DynamicSvr;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiDynamicSvr
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function followUpDynamic(): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/dynamic_new', [
                'uid' => $this->request->uidValue(),
                'type_list' => '8,512,4097,4098,4099,4100,4101',
            ], [
                'origin' => 'https://t.bilibili.com',
                'referer' => 'https://t.bilibili.com/pages/nav/index_new',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'dynamic_svr.follow_up_dynamic 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'dynamic_svr.follow_up_dynamic');
    }
}
