<?php declare(strict_types=1);

namespace Bhp\Api\Space;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiArticle
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function article(string $uid, int $pn = 1, int $ps = 2, string $sort = 'publish_time'): array
    {
        try {
            $raw = $this->request->getText('other', 'https://api.bilibili.com/x/space/article', [
                'mid' => $uid,
                'pn' => $pn,
                'ps' => $ps,
                'sort' => $sort,
            ], [
                'origin' => 'https://space.bilibili.com',
                'referer' => "https://space.bilibili.com/{$uid}/",
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'space.article 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'space.article');
    }
}
