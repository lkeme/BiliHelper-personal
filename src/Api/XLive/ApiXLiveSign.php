<?php declare(strict_types=1);

namespace Bhp\Api\XLive;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiXLiveSign
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function webGetSignInfo(): array
    {
        return $this->decodeGet('https://api.live.bilibili.com/xlive/web-ucenter/v1/sign/WebGetSignInfo', 'xlive.sign.info');
    }

    /**
     * @return array<string, mixed>
     */
    public function doSign(): array
    {
        return $this->decodeGet('https://api.live.bilibili.com/xlive/web-ucenter/v1/sign/DoSign', 'xlive.sign.do_sign');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeGet(string $url, string $label): array
    {
        try {
            $raw = $this->request->getText('pc', $url, [], [
                'origin' => 'https://link.bilibili.com',
                'referer' => 'https://link.bilibili.com/p/center/index',
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, $label);
    }
}
