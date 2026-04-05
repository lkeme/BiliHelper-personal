<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\Task;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

final class ApiTask
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @param string[] $taskIds
     * @return array<string, mixed>
     */
    public function totalV2(array $taskIds, bool $needAllInvitedInfo = false): array
    {
        $taskIds = array_values(array_filter(array_map(static fn (mixed $taskId): string => trim((string)$taskId), $taskIds)));
        if ($taskIds === []) {
            return [
                'code' => -400,
                'message' => 'task_ids 不能为空',
                'data' => [],
            ];
        }

        return $this->decodeGet('pc', 'https://api.bilibili.com/x/task/totalv2', [
            'task_ids' => implode(',', $taskIds),
            'need_all_invited_info' => $needAllInvitedInfo ? 1 : 0,
            'web_location' => '0.0',
            'csrf' => $this->request->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'x.task.totalv2');
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
}
