<?php declare(strict_types=1);

namespace Bhp\Api\Video;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiLike extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function like(string $aid = '', string $bvid = '', int $like = 1): array
    {
        $referer = $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}";

        return $this->decodePost('pc', 'https://api.bilibili.com/x/web-interface/archive/like', [
            'aid' => $aid,
            'bvid' => $bvid,
            'like' => $like,
            'csrf' => $this->request()->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => $referer,
        ], 'video.like');
    }

    /**
     * @return array<string, mixed>
     */
    public function hasLike(string $aid = '', string $bvid = ''): array
    {
        $referer = $bvid !== '' ? "https://www.bilibili.com/video/{$bvid}" : "https://www.bilibili.com/video/av{$aid}";

        return $this->decodeGet('pc', 'https://api.bilibili.com/x/web-interface/archive/has/like', [
            'aid' => $aid,
            'bvid' => $bvid,
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => $referer,
        ], 'video.has_like');
    }
}
