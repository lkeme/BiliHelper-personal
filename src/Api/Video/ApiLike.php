<?php declare(strict_types=1);

namespace Bhp\Api\Video;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

final class ApiLike
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function like(string $aid = '', string $bvid = '', int $like = 1): array
    {
        $referer = $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}";

        try {
            $raw = $this->request->postText('pc', 'https://api.bilibili.com/x/web-interface/archive/like', [
                'aid' => $aid,
                'bvid' => $bvid,
                'like' => $like,
                'csrf' => $this->request->csrfValue(),
            ], [
                'origin' => 'https://www.bilibili.com',
                'referer' => $referer,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'video.like 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'video.like');
    }

    /**
     * @return array<string, mixed>
     */
    public function hasLike(string $aid = '', string $bvid = ''): array
    {
        $referer = $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}";

        try {
            $raw = $this->request->getText('pc', 'https://api.bilibili.com/x/web-interface/archive/has/like', [
                'aid' => $aid,
                'bvid' => $bvid,
            ], [
                'origin' => 'https://www.bilibili.com',
                'referer' => $referer,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'video.has_like 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'video.has_like');
    }
}
