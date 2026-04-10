<?php declare(strict_types=1);

namespace Bhp\Api\Api\X\Task;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiTask extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
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
            'csrf' => $this->request()->csrfValue(),
        ], [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ], 'x.task.totalv2');
    }
}
