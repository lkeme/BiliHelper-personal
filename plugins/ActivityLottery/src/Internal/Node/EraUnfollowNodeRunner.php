<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Util\Exceptions\RequestException;

final class EraUnfollowNodeRunner implements NodeRunnerInterface
{
    private const STEP_DELAY_SECONDS = 15;
    private const RETRY_DELAY_SECONDS = 300;

    /**
     * @var callable(int): array<string, mixed>
     */
    private readonly mixed $unfollowAction;
    private readonly AuthFailureClassifier $authFailureClassifier;
    private readonly ?ApiRelation $apiRelation;

    /**
     * 初始化 EraUnfollowNodeRunner
     * @param callable $unfollowAction
     * @param AuthFailureClassifier $authFailureClassifier
     * @param ApiRelation $apiRelation
     */
    public function __construct(?callable $unfollowAction = null, ?AuthFailureClassifier $authFailureClassifier = null, ?ApiRelation $apiRelation = null)
    {
        $this->apiRelation = $apiRelation;
        $this->unfollowAction = $unfollowAction ?? function (int $uid): array {
            if (!$this->apiRelation instanceof ApiRelation) {
                throw new \LogicException('EraUnfollowNodeRunner requires an ApiRelation dependency.');
            }

            return $this->apiRelation->modify($uid);
        };
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    /**
     * 获取类型标识
     * @return string
     */
    public function type(): string
    {
        return 'era_task_unfollow';
    }

    /**
     * 启动执行流程
     * @param ActivityFlow $flow
     * @param ActivityNode $node
     * @param int $now
     * @return ActivityNodeResult
     */
    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();

        if ($task === null || $taskView->taskId() === '') {
            return new ActivityNodeResult(true, '取消关注任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        return $this->runExplicitUnfollow($taskView, $task, $now);
    }

    /**
     * 处理运行Explicit取关
     * @param ResolvedEraTaskView $taskView
     * @param \Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot $task
     * @param int $now
     * @return ActivityNodeResult
     */
    private function runExplicitUnfollow(ResolvedEraTaskView $taskView, \Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot $task, int $now): ActivityNodeResult
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
        try {
            $response = (array)($this->unfollowAction)((int)$uid);
        } catch (RequestException $exception) {
            return new ActivityNodeResult(false, '取消关注任务执行失败: ' . $exception->getMessage(), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }

        $this->authFailureClassifier->assertNotAuthFailure($response, '取消关注任务执行时账号未登录');
        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            return new ActivityNodeResult(false, '取消关注任务执行失败，稍后重试', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }
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

