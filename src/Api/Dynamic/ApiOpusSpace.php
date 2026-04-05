<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;
use Throwable;

final class ApiOpusSpace
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function feed(string $hostMid, string $offset = '', int $page = 1, string $type = 'all'): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.bilibili.com/x/polymer/web-dynamic/v1/opus/feed/space', WbiSign::encryption([
                'host_mid' => $hostMid,
                'page' => $page,
                'offset' => $offset,
                'type' => $type,
                'web_location' => '333.1387',
            ]), [
                'origin' => 'https://space.bilibili.com',
                'referer' => 'https://space.bilibili.com/' . $hostMid . '/dynamic',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'dynamic.opus.space 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'dynamic.opus.space');
    }
}
