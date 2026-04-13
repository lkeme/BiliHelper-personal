<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Api\Api\X\Activity\ApiActivity;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Util\Exceptions\RequestException;

final class EraShareNodeRunner implements NodeRunnerInterface
{
    private const RETRY_DELAY_SECONDS = 300;

    private readonly ApiActivity $apiActivity;
    /**
     * @var callable(string, string, string): array<string, mixed>
     */
    private readonly mixed $shareAction;
    private readonly AuthFailureClassifier $authFailureClassifier;

    public function __construct(ApiActivity $apiActivity, ?callable $shareAction = null, ?AuthFailureClassifier $authFailureClassifier = null)
    {
        $this->apiActivity = $apiActivity;
        $this->shareAction = $shareAction ?? fn (string $taskId, string $counter, string $url): array => $this->apiActivity->sendPoints($taskId, $counter, $url);
        $this->authFailureClassifier = $authFailureClassifier ?? new AuthFailureClassifier();
    }

    public function type(): string
    {
        return 'era_task_share';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if ($taskView->taskId() === '' || $task === null) {
            return new ActivityNodeResult(true, '分享任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $activity = ResolvedActivityView::fromFlow($flow);
        $url = $activity->url();
        if ($url === '') {
            $url = 'https://www.bilibili.com/';
        }

        try {
            $response = (array)($this->shareAction)($task->taskId(), $task->counter(), $url);
        } catch (RequestException $exception) {
            return new ActivityNodeResult(false, '分享任务上报失败: ' . $exception->getMessage(), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }

        $this->authFailureClassifier->assertNotAuthFailure($response, '分享任务上报时账号未登录');
        $code = (int)($response['code'] ?? -1);
        if ($code === -500) {
            return new ActivityNodeResult(false, '分享任务上报失败，稍后重试', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }
        if ($code !== 0) {
            return new ActivityNodeResult(false, '分享任务上报失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        return new ActivityNodeResult(true, '分享任务执行完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $taskView->patchTaskRuntime([
                'share_reported_at' => $now,
            ]),
        ], $now);
    }
}

