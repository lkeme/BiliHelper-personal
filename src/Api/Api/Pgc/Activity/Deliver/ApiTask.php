<?php declare(strict_types=1);

namespace Bhp\Api\Api\Pgc\Activity\Deliver;

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
    public function complete(string $position): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/deliver/task/complete', $this->request->signCommonPayload([
            'disable_rcmd' => '0',
            'position' => $position,
            'csrf' => $this->request->csrfValue(),
        ], true), self::HEADERS, 'pgc.deliver.complete');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodePost(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->postText($os, $url, $payload, $headers);
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
