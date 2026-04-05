<?php declare(strict_types=1);

namespace Bhp\Api\XLive\AppUcenter\V1;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiUserTask
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserTaskProgress(int $upId): array
    {
        return $this->decodeGet('app', 'https://api.live.bilibili.com/xlive/app-ucenter/v1/userTask/GetUserTaskProgress', $this->request->signCommonPayload([
            'target_id' => $upId,
        ], true), [], 'xlive.user_task.progress');
    }

    /**
     * @return array<string, mixed>
     */
    public function userTaskReceiveRewards(int $upId): array
    {
        return $this->decodePost('app', 'https://api.live.bilibili.com/xlive/app-ucenter/v1/userTask/UserTaskReceiveRewards', $this->request->signCommonPayload([
            'target_id' => $upId,
        ], true), [], 'xlive.user_task.receive_rewards');
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
