<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;
use Bhp\WbiSign\WbiSign;

final class ApiOpusSpace extends AbstractApiClient
{
    /**
     * 初始化 ApiOpusSpace
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
    public function feed(string $hostMid, string $offset = '', int $page = 1, string $type = 'all'): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/polymer/web-dynamic/v1/opus/feed/space', WbiSign::encryption([
            'host_mid' => $hostMid,
            'page' => $page,
            'offset' => $offset,
            'type' => $type,
            'web_location' => '333.1387',
        ]), [
            'origin' => 'https://space.bilibili.com',
            'referer' => 'https://space.bilibili.com/' . $hostMid . '/dynamic',
        ], 'dynamic.opus.space');
    }
}
