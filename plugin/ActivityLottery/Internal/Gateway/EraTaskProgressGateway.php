<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Gateway;

use Bhp\Api\Api\X\Task\ApiTask;
use Bhp\Login\AuthFailureClassifier;

final class EraTaskProgressGateway
{
    /**
     * @var callable(string[], bool): array<string, mixed>
     */
    private readonly mixed $taskProgressFetcher;
    private readonly AuthFailureClassifier $authFailureClassifier;

    public function __construct(
        ?callable $taskProgressFetcher = null,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->taskProgressFetcher = $taskProgressFetcher ?? static fn (array $taskIds, bool $needAllInvitedInfo = false): array => ApiTask::totalV2($taskIds, $needAllInvitedInfo);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    /**
     * @param string[] $taskIds
     * @return array<string, array<string, mixed>>
     */
    public function fetchSnapshots(array $taskIds, bool $needAllInvitedInfo = false): array
    {
        $taskIds = array_values(array_filter(array_map(static fn (mixed $taskId): string => trim((string)$taskId), $taskIds)));
        if ($taskIds === []) {
            return [];
        }

        $response = (array)($this->taskProgressFetcher)($taskIds, $needAllInvitedInfo);
        $this->authFailureClassifier->assertNotAuthFailure($response, '同步任务进度时账号未登录');
        if ((int)($response['code'] ?? -1) !== 0) {
            return [];
        }

        $snapshots = [];
        foreach (is_array($response['data']['list'] ?? null) ? $response['data']['list'] : [] as $task) {
            if (!is_array($task)) {
                continue;
            }

            $taskId = trim((string)($task['task_id'] ?? ''));
            if ($taskId === '') {
                continue;
            }

            $snapshots[$taskId] = $task;
        }

        return $snapshots;
    }
}
