<?php declare(strict_types=1);

namespace Bhp\Api\Video;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\User\User;

final class ApiLike
{
    /**
     * @return array<string, mixed>
     */
    public static function like(string $aid = '', string $bvid = '', int $like = 1): array
    {
        $user = User::parseCookie();
        $url = 'https://api.bilibili.com/x/web-interface/archive/like';
        $payload = [
            'aid' => $aid,
            'bvid' => $bvid,
            'like' => $like,
            'csrf' => $user['csrf'],
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}",
        ];

        return ApiJson::post('pc', $url, $payload, $headers, 'video.like');
    }

    /**
     * @return array<string, mixed>
     */
    public static function hasLike(string $aid = '', string $bvid = ''): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/archive/has/like';
        $payload = [
            'aid' => $aid,
            'bvid' => $bvid,
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}",
        ];

        return ApiJson::get('pc', $url, $payload, $headers, 'video.has_like');
    }
}
