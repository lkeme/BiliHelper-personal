<?php declare(strict_types=1);

namespace Bhp\Api\Space;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiArticle extends AbstractApiClient
{
    /**
     * 初始化 ApiArticle
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
    public function article(string $uid, int $pn = 1, int $ps = 2, string $sort = 'publish_time'): array
    {
        return $this->decodeGet('other', 'https://api.bilibili.com/x/space/article', [
            'mid' => $uid,
            'pn' => $pn,
            'ps' => $ps,
            'sort' => $sort,
        ], [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$uid}/",
        ], 'space.article');
    }
}
