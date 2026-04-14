<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\Player;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiPlayer extends AbstractApiClient
{
    /**
     * 初始化 ApiPlayer
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
    public function pageList(string $aid = '', string $bvid = ''): array
    {
        $payload = [];
        if ($aid !== '') {
            $payload['aid'] = $aid;
        }
        if ($bvid !== '') {
            $payload['bvid'] = $bvid;
        }

        return $this->decodeGet('other', 'https://api.bilibili.com/x/player/pagelist', $payload, [], 'x.player.pagelist');
    }
}
