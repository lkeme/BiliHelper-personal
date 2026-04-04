<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;

final class EraUnfollowNodeRunner implements NodeRunnerInterface
{
    private const STEP_DELAY_SECONDS = 15;

    /**
     * @var callable(int): array<string, mixed>
     */
    private readonly mixed $unfollowAction;
    private readonly AuthFailureClassifier $authFailureClassifier;

    public function __construct(?callable $unfollowAction = null, ?AuthFailureClassifier $authFailureClassifier = null)
    {
        $this->unfollowAction = $unfollowAction ?? static fn (int $uid): array => ApiRelation::modify($uid);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    public function type(): string
    {
        return 'era_task_unfollow';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();

        if ($task !== null && $taskView->taskId() !== '') {
            return $this->runExplicitUnfollow($taskView, $task, $now);
        }

        return $this->runTemporaryFollowCleanup($flow, $now);
    }

    private function runExplicitUnfollow(ResolvedEraTaskView $taskView, \Bhp\Plugin\ActivityLottery\Internal\Page\EraTaskSnapshot $task, int $now): ActivityNodeResult
    {
        if ($taskView->resolvedTaskStatus() !== 1) {
            return new ActivityNodeResult(true, '取消关注任务已完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
            ], $now);
        }

        $uids = $task->targetUids();
        if ($uids === []) {
            return new ActivityNodeResult(true, '取消关注任务缺少目标 UID，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $state = $taskView->taskRuntime();
        $index = max(0, (int)($state['unfollow_target_index'] ?? 0));
        if (!isset($uids[$index])) {
            return new ActivityNodeResult(true, '取消关注任务已完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
            ], $now);
        }

        $uid = (string)$uids[$index];
        $response = (array)($this->unfollowAction)((int)$uid);
        $this->authFailureClassifier->assertNotAuthFailure($response, '取消关注任务执行时账号未登录');
        if (!$this->isSuccessfulResponse($response)) {
            return new ActivityNodeResult(false, '取消关注任务执行失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $nextState = array_replace($state, [
            'unfollow_target_index' => $index + 1,
        ]);

        if (($index + 1) < count($uids)) {
            return new ActivityNodeResult(true, '取消关注任务已推进到下一目标', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::STEP_DELAY_SECONDS,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        return new ActivityNodeResult(true, '取消关注任务执行完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $taskView->replaceTaskRuntime($nextState),
        ], $now);
    }

    private function runTemporaryFollowCleanup(ActivityFlow $flow, int $now): ActivityNodeResult
    {
        $context = $flow->context()->toArray();
        $runtimeMap = is_array($context['era_task_runtime'] ?? null) ? $context['era_task_runtime'] : [];
        $targets = [];
        foreach ($runtimeMap as $taskState) {
            if (!is_array($taskState)) {
                continue;
            }

            foreach (is_array($taskState['temporary_follow_uids'] ?? null) ? $taskState['temporary_follow_uids'] : [] as $uid) {
                $uid = trim((string)$uid);
                if ($uid !== '') {
                    $targets[$uid] = true;
                }
            }
        }

        if ($targets === []) {
            return new ActivityNodeResult(true, '没有待取消关注的账号', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $done = [];
        foreach (is_array($context['follow_cleanup_done_uids'] ?? null) ? $context['follow_cleanup_done_uids'] : [] as $uid) {
            $uid = trim((string)$uid);
            if ($uid !== '') {
                $done[$uid] = true;
            }
        }

        $pending = array_values(array_filter(array_keys($targets), static fn (string $uid): bool => !isset($done[$uid])));
        if ($pending === []) {
            return new ActivityNodeResult(true, '临时关注回收完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
                'context_patch' => $this->cleanupTemporaryFollowContext($context, array_keys($targets)),
            ], $now);
        }

        $uid = (string)$pending[0];
        $response = (array)($this->unfollowAction)((int)$uid);
        $this->authFailureClassifier->assertNotAuthFailure($response, '回收临时关注时账号未登录');
        if (!$this->isSuccessfulResponse($response)) {
            return new ActivityNodeResult(false, '临时关注回收失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $done[$uid] = true;
        if (count($done) < count($targets)) {
            return new ActivityNodeResult(true, '临时关注回收到下一目标', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::STEP_DELAY_SECONDS,
                'context_patch' => [
                    'follow_cleanup_done_uids' => array_values(array_keys($done)),
                ],
            ], $now);
        }

        return new ActivityNodeResult(true, '临时关注回收完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $this->cleanupTemporaryFollowContext($context, array_keys($targets)),
        ], $now);
    }

    /**
     * @param array<string, mixed> $context
     * @param string[] $targets
     * @return array<string, mixed>
     */
    private function cleanupTemporaryFollowContext(array $context, array $targets): array
    {
        $runtimeMap = is_array($context['era_task_runtime'] ?? null) ? $context['era_task_runtime'] : [];
        foreach ($runtimeMap as $taskId => $taskState) {
            if (!is_array($taskState)) {
                continue;
            }

            unset($taskState['temporary_follow_uids']);
            $runtimeMap[$taskId] = $taskState;
        }

        return [
            'era_task_runtime' => $runtimeMap,
            'follow_cleanup_done_uids' => array_values($targets),
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function isSuccessfulResponse(array $response): bool
    {
        $code = (int)($response['code'] ?? -1);
        $message = trim((string)($response['message'] ?? $response['msg'] ?? ''));

        return $code === 0 || str_contains($message, '未关注') || str_contains($message, '重复');
    }
}
