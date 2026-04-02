<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\Task;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Bhp\User\User;

final class ApiTask
{
    /**
     * @param string[] $taskIds
     * @return array<string, mixed>
     */
    public static function totalV2(array $taskIds, bool $needAllInvitedInfo = false): array
    {
        $taskIds = array_values(array_filter(array_map(static fn (mixed $taskId): string => trim((string)$taskId), $taskIds)));
        if ($taskIds === []) {
            return [
                'code' => -400,
                'message' => 'task_ids 不能为空',
                'data' => [],
            ];
        }

        $user = User::parseCookie();
        $url = 'https://api.bilibili.com/x/task/totalv2';
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ];
        $payload = [
            'task_ids' => implode(',', $taskIds),
            'need_all_invited_info' => $needAllInvitedInfo ? 1 : 0,
            'web_location' => '0.0',
            'csrf' => $user['csrf'],
        ];

        return ApiJson::get('pc', $url, $payload, $headers, 'x.task.totalv2');
    }
}
