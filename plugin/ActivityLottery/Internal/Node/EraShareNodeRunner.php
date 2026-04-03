<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Api\Api\X\Activity\ApiActivity;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;

final class EraShareNodeRunner implements NodeRunnerInterface
{
    /**
     * @var callable(string, string, string): array<string, mixed>
     */
    private readonly mixed $shareAction;

    public function __construct(?callable $shareAction = null)
    {
        $this->shareAction = $shareAction ?? static fn (string $taskId, string $counter, string $url): array => ApiActivity::sendPoints($taskId, $counter, $url);
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

        $response = (array)($this->shareAction)($task->taskId(), $task->counter(), $url);
        if ((int)($response['code'] ?? -1) !== 0) {
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
