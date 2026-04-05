<?php declare(strict_types=1);

namespace Bhp\Api\XLive\AppUcenter\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiFansMedal
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function panel(int $pn, int $ps): array
    {
        return $this->decodeGet('app', 'https://api.live.bilibili.com/xlive/app-ucenter/v1/fansMedal/panel', $this->request->signCommonPayload([
            'page' => $pn,
            'page_size' => $ps,
        ]), [], 'xlive.fans_medal.panel');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodeGet(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->getText($os, $url, $payload, $headers);
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
