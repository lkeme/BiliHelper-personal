<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\Player;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiPlayer
{
    public function __construct(
        private readonly Request $request,
    ) {
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

        try {
            $raw = $this->request->getText('other', 'https://api.bilibili.com/x/player/pagelist', $payload);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'x.player.pagelist 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'x.player.pagelist');
    }
}
