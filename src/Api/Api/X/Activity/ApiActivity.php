<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\Activity;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiActivity
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    public function doLottery(array $info, int $num = 1): array
    {
        $payload = [
            'sid' => $info['sid'],
            'num' => $num,
            'page_id' => (string)($info['page_id'] ?? ''),
            'csrf' => $this->request->csrfValue(),
        ];
        if (trim((string)($info['gaia_vtoken'] ?? '')) !== '') {
            $payload['gaia_vtoken'] = trim((string)$info['gaia_vtoken']);
        }

        return $this->decodePost('pc', 'https://api.bilibili.com/x/lottery/x/do', $payload, [
            'origin' => 'https://www.bilibili.com',
            'referer' => (string)$info['url'],
        ], 'x.lottery.x.do');
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    public function addTimes(array $info, int $actionType = 3): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/lottery/addtimes', [
            'sid' => $info['sid'],
            'action_type' => $actionType,
            'csrf' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => (string)$info['url'],
        ], 'x.lottery.addtimes');
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    public function myTimes(array $info): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/lottery/x/mytimes', [
            'sid' => $info['sid'],
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => (string)$info['url'],
        ], 'x.lottery.x.mytimes');
    }

    /**
     * @return array<string, mixed>
     */
    public function sendPoints(string $taskId, string $counter, string $referer = 'https://www.bilibili.com/'): array
    {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/activity/task/send_points', [
            'activity' => $taskId,
            'business' => $counter,
            'timestamp' => (int)round(microtime(true) * 1000),
            'csrf' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => $referer,
        ], 'x.activity.send_points');
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
