<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\VipPoint;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiTask
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task',
    ];

    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function combine(): array
    {
        return $this->decodeGet('app', 'https://api.bilibili.com/x/vip_point/task/combine', $this->request->signCommonPayload([]), self::HEADERS, 'vip_point.task.combine');
    }

    /**
     * @return array<string, mixed>
     */
    public function homepageCombine(): array
    {
        return $this->decodeGet('app', 'https://api.bilibili.com/x/vip_point/homepage/combine', $this->request->signCommonPayload([]), self::HEADERS, 'vip_point.homepage.combine');
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
