<?php declare(strict_types=1);

namespace Bhp\Api\Vip;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiPrivilege
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function my(): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/vip/privilege/my', [], [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/big/myPackage',
        ], 'vip.privilege.my');
    }

    /**
     * @return array<string, mixed>
     */
    public function receive(int $type): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/vip/privilege/receive', [
            'type' => $type,
            'csrf' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/big/myPackage',
        ], 'vip.privilege.receive');
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
