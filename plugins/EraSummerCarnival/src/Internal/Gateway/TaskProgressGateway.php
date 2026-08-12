<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Gateway;

use Bhp\Api\Api\X\Task\ApiTask;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Util\Exceptions\RequestException;

/**
 * totalv2 任务进度批量查询。
 *
 * 本插件不复用 ActivityLottery\Internal\Gateway\EraTaskProgressGateway：
 * 那会形成跨插件硬依赖（ActivityLottery 过期后其类不再加载）。
 * 该映射逻辑仅约 30 行，重复成本远低于 Follow 队列，故就地实现。
 */
final class TaskProgressGateway
{
    private readonly ApiTask $apiTask;
    private readonly AuthFailureClassifier $authFailureClassifier;

    public function __construct(
        ApiTask $apiTask,
        ?AuthFailureClassifier $authFailureClassifier = null,
    ) {
        $this->apiTask = $apiTask;
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    /**
     * @param string[] $taskIds
     * @return array<string, array<string, mixed>> taskId => 任务详情
     */
    public function fetch(array $taskIds): array
    {
        $taskIds = array_values(array_filter(array_map(
            static fn (mixed $taskId): string => trim((string)$taskId),
            $taskIds,
        ), static fn (string $taskId): bool => $taskId !== ''));
        if ($taskIds === []) {
            return [];
        }

        $response = $this->apiTask->totalV2($taskIds);
        $this->authFailureClassifier->assertNotAuthFailure($response, '同步任务进度时账号未登录');

        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            throw new RequestException(sprintf(
                '次元奇旅: 同步任务进度失败 code=%s message=%s',
                (string)$code,
                (string)($response['message'] ?? $response['msg'] ?? ''),
            ));
        }
        if ($code !== 0) {
            return [];
        }

        $list = $response['data']['list'] ?? null;
        if (!is_array($list)) {
            return [];
        }

        $snapshots = [];
        foreach ($list as $task) {
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
