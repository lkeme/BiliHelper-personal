<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Request\Request;

final class ApiDetail
{
    /**
     * @return array<string, mixed>
     */
    public static function detail(int $dynamicId): array
    {
        return Request::getJson(true, 'pc', 'https://api.bilibili.com/x/polymer/web-dynamic/v1/detail', [
            'timezone_offset' => '-480',
            'id' => $dynamicId,
            'features' => 'itemOpusStyle',
        ], [
            'origin' => 'https://t.bilibili.com',
            'referer' => 'https://t.bilibili.com/' . $dynamicId,
        ]);
    }
}
