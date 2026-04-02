<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\ApiJson;
use Bhp\WbiSign\WbiSign;

final class ApiOpusSpace
{
    /**
     * @return array<string, mixed>
     */
    public static function feed(
        string $hostMid,
        string $offset = '',
        int $page = 1,
        string $type = 'all',
    ): array {
        $url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/opus/feed/space';
        $payload = [
            'host_mid' => $hostMid,
            'page' => $page,
            'offset' => $offset,
            'type' => $type,
            'web_location' => '333.1387',
        ];
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => 'https://space.bilibili.com/' . $hostMid . '/dynamic',
        ];

        return ApiJson::get('pc', $url, WbiSign::encryption($payload), $headers, 'dynamic.opus.space');
    }
}
