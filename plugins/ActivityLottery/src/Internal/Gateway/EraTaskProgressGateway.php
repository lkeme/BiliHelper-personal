<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway;

use Bhp\Api\Api\X\Task\ApiTask;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Util\Exceptions\RequestException;

final class EraTaskProgressGateway
{
    /**
     * @var callable(string[], bool): array<string, mixed>
     */
    private readonly mixed $taskProgressFetcher;
    private readonly AuthFailureClassifier $authFailureClassifier;

    /**
     * 初始化 EraTaskProgressGateway
     * @param ApiTask $apiTask
     * @param callable $taskProgressFetcher
     * @param AuthFailureClassifier $authFailureClassifier
     */
    public function __construct(
        ApiTask $apiTask,
        ?callable $taskProgressFetcher = null,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->taskProgressFetcher = $taskProgressFetcher ?? fn (array $taskIds, bool $needAllInvitedInfo = false): array => $apiTask->totalV2($taskIds, $needAllInvitedInfo);
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
        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            throw new RequestException(sprintf(
                '同步任务进度失败 task_ids=%s code=%s message=%s',
                implode(',', $taskIds),
                (string)$code,
                (string)($response['message'] ?? $response['msg'] ?? '')
            ));
        }
        if ($code !== 0) {
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

