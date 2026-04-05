<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

final class ApiDetail
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(int $dynamicId): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.bilibili.com/x/polymer/web-dynamic/v1/detail', [
                'timezone_offset' => '-480',
                'id' => $dynamicId,
                'features' => 'itemOpusStyle',
            ], [
                'origin' => 'https://t.bilibili.com',
                'referer' => 'https://www.bilibili.com/opus/' . $dynamicId,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'dynamic.detail 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'dynamic.detail');
    }
}
