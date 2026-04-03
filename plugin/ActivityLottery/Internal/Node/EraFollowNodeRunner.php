<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;

final class EraFollowNodeRunner implements NodeRunnerInterface
{
    private const STEP_DELAY_SECONDS = 15;

    /**
     * @var callable(int): array<string, mixed>
     */
    private readonly mixed $followAction;

    public function __construct(?callable $followAction = null)
    {
        $this->followAction = $followAction ?? static fn (int $uid): array => ApiRelation::follow($uid, ApiRelation::SOURCE_ACTIVITY_PAGE);
    }

    public function type(): string
    {
        return 'era_task_follow';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if ($taskView->taskId() === '' || $task === null) {
            return new ActivityNodeResult(true, '关注任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $uids = $task->targetUids();
        if ($uids === []) {
            return new ActivityNodeResult(true, '关注任务缺少目标 UID，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $state = $taskView->taskRuntime();
        $index = max(0, (int)($state['follow_target_index'] ?? 0));
        if (!isset($uids[$index])) {
            return new ActivityNodeResult(true, '关注任务已完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
            ], $now);
        }

        $uid = (string)$uids[$index];
        $response = (array)($this->followAction)((int)$uid);
        if (!$this->isSuccessfulResponse($response)) {
            return new ActivityNodeResult(false, '关注任务执行失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $temporaryUids = is_array($state['temporary_follow_uids'] ?? null)
            ? $state['temporary_follow_uids']
            : [];
        $temporaryUids[] = $uid;

        $nextState = array_replace($state, [
            'follow_target_index' => $index + 1,
            'temporary_follow_uids' => array_values(array_unique(array_map('strval', $temporaryUids))),
        ]);

        if (($index + 1) < count($uids)) {
            return new ActivityNodeResult(true, '关注任务已推进到下一目标', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::STEP_DELAY_SECONDS,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        return new ActivityNodeResult(true, '关注任务执行完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $taskView->replaceTaskRuntime($nextState),
        ], $now);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isSuccessfulResponse(array $response): bool
    {
        $code = (int)($response['code'] ?? -1);
        $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));
        return $code === 0 || $code === 22014 || str_contains($message, '已关注');
    }
}
