<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\ActivityComponents;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\User\User;

final class ApiMission
{
    /**
     * @return array<string, mixed>
     */
    public static function info(string $taskId): array
    {
        $url = 'https://api.bilibili.com/x/activity_components/mission/info';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => "https://www.bilibili.com/blackboard/era/award-exchange.html?task_id={$taskId}",
        ];
        $payload = [
            'task_id' => $taskId,
        ];

        return ApiJson::get('pc', $url, $payload, $headers, 'activity_components.mission.info');
    }

    /**
     * @return array<string, mixed>
     */
    public static function receive(
        string $taskId,
        string $activityId,
        string $activityName,
        string $taskName,
        string $rewardName,
        string $gaiaVToken = '',
    ): array {
        $user = User::parseCookie();
        $url = 'https://api.bilibili.com/x/activity_components/mission/receive';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => "https://www.bilibili.com/blackboard/era/award-exchange.html?task_id={$taskId}",
        ];
        $payload = [
            'task_id' => $taskId,
            'activity_id' => $activityId,
            'activity_name' => $activityName,
            'task_name' => $taskName,
            'reward_name' => $rewardName,
            'gaia_vtoken' => $gaiaVToken,
            'receive_from' => 'missionPage',
            'csrf' => $user['csrf'],
        ];

        return ApiJson::post('pc', $url, $payload, $headers, 'activity_components.mission.receive');
    }
}
