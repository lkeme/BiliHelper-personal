<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiDetail extends AbstractApiClient
{
    /**
     * 初始化 ApiDetail
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
    public function detail(int $dynamicId): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/polymer/web-dynamic/v1/detail', [
            'timezone_offset' => '-480',
            'id' => $dynamicId,
            'features' => 'itemOpusStyle',
        ], [
            'origin' => 'https://t.bilibili.com',
            'referer' => 'https://www.bilibili.com/opus/' . $dynamicId,
        ], 'dynamic.detail');
    }
}
