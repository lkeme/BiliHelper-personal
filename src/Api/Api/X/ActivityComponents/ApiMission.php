<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\ActivityComponents;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

final class ApiMission
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function info(string $taskId): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/activity_components/mission/info', [
            'task_id' => $taskId,
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => "https://www.bilibili.com/blackboard/era/award-exchange.html?task_id={$taskId}",
        ], 'activity_components.mission.info');
    }

    /**
     * @return array<string, mixed>
     */
    public function receive(
        string $taskId,
        string $activityId,
        string $activityName,
        string $taskName,
        string $rewardName,
        string $gaiaVToken = '',
    ): array {
        return $this->decodePost('pc', 'https://api.bilibili.com/x/activity_components/mission/receive', [
            'task_id' => $taskId,
            'activity_id' => $activityId,
            'activity_name' => $activityName,
            'task_name' => $taskName,
            'reward_name' => $rewardName,
            'gaia_vtoken' => $gaiaVToken,
            'receive_from' => 'missionPage',
            'csrf' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => "https://www.bilibili.com/blackboard/era/award-exchange.html?task_id={$taskId}",
        ], 'activity_components.mission.receive');
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
