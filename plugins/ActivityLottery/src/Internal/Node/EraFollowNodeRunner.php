<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Util\Exceptions\RequestException;

final class EraFollowNodeRunner implements NodeRunnerInterface
{
    private const STEP_DELAY_SECONDS = 15;
    private const RETRY_DELAY_SECONDS = 300;
    private const RELATION_SOURCE_ACTIVITY_PAGE = 222;

    /**
     * @var callable(int): array<string, mixed>
     */
    private readonly mixed $followAction;
    private readonly AuthFailureClassifier $authFailureClassifier;
    private readonly ?ApiRelation $apiRelation;

    public function __construct(?callable $followAction = null, ?AuthFailureClassifier $authFailureClassifier = null, ?ApiRelation $apiRelation = null)
    {
        $this->apiRelation = $apiRelation;
        $this->followAction = $followAction ?? function (int $uid): array {
            if (!$this->apiRelation instanceof ApiRelation) {
                throw new \LogicException('EraFollowNodeRunner requires an ApiRelation dependency.');
            }

            return $this->apiRelation->follow($uid, self::RELATION_SOURCE_ACTIVITY_PAGE);
        };
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
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

        if ($taskView->resolvedTaskStatus() !== 1) {
            return new ActivityNodeResult(true, '关注任务已完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
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
        try {
            $response = (array)($this->followAction)((int)$uid);
        } catch (RequestException $exception) {
            return new ActivityNodeResult(false, '关注任务执行失败: ' . $exception->getMessage(), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }

        $this->authFailureClassifier->assertNotAuthFailure($response, '关注任务执行时账号未登录');
        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            return new ActivityNodeResult(false, '关注任务执行失败，稍后重试', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }
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

