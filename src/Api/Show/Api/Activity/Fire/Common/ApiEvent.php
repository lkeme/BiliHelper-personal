<?php declare(strict_types=1);

namespace Bhp\Api\Show\Api\Activity\Fire\Common;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiEvent
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
    public function dispatch(): array
    {
        $url = 'https://show.bilibili.com/api/activity/fire/common/event/dispatch?' . http_build_query(
            $this->request->signCommonPayload([
                'csrf' => $this->request->csrfValue(),
            ], true)
        );

        return $this->decodePost('app', $url, [
            'eventId' => 'hevent_oy4b7h3epeb',
        ], array_merge([
            'content-type' => 'application/json; charset=utf-8',
        ], self::HEADERS), 'show.activity.fire.dispatch');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodePost(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->postJsonBodyText($os, $url, $payload, $headers);
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
