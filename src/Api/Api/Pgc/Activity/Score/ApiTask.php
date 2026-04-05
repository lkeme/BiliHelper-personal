<?php declare(strict_types=1);

namespace Bhp\Api\Api\Pgc\Activity\Score;

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
    public function sign(): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/score/task/sign2', $this->request->signCommonPayload([
            'disable_rcmd' => '0',
            'buvid' => $this->request->buvidValue(),
            'csrf' => $this->request->csrfValue(),
        ], true), self::HEADERS, 'pgc.score.sign');
    }

    /**
     * @return array<string, mixed>
     */
    public function receive(string $taskCode): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/score/task/receive', $this->request->signCommonPayload([
            'taskCode' => $taskCode,
            'csrf' => $this->request->csrfValue(),
        ], true), self::HEADERS, 'pgc.score.receive');
    }

    /**
     * @return array<string, mixed>
     */
    public function complete(string $taskCode): array
    {
        return $this->decodePost('app', 'https://api.bilibili.com/pgc/activity/score/task/complete', $this->request->signCommonPayload([
            'taskCode' => $taskCode,
            'csrf' => $this->request->csrfValue(),
            'ts' => time(),
        ], true), array_merge([
            'Content-Type' => 'application/json',
        ], self::HEADERS), 'pgc.score.complete');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodePost(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = str_contains(strtolower((string)($headers['Content-Type'] ?? $headers['content-type'] ?? '')), 'application/json')
                ? $this->request->postJsonBodyText($os, $url, $payload, $headers)
                : $this->request->postText($os, $url, $payload, $headers);
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
