<?php declare(strict_types=1);

namespace Bhp\Api\Space;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiReservation
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function reservation(string $vmid): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/space/reservation', [
            'vmid' => $vmid,
        ], [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$vmid}/",
        ], 'space.reservation.list');
    }

    /**
     * @return array<string, mixed>
     */
    public function reserve(int $sid, int $vmid): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/space/reserve', [
            'sid' => $sid,
            'jsonp' => 'jsonp',
            'csrf' => $this->request->csrfValue(),
        ], [
            'content-type' => 'application/x-www-form-urlencoded',
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/{$vmid}/",
        ], 'space.reservation.reserve');
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
