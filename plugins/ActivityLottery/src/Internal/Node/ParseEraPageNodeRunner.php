<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\EraTaskProgressGateway;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraPageParser;
use Bhp\Util\Exceptions\RequestException;

final class ParseEraPageNodeRunner implements NodeRunnerInterface
{
    private const RETRY_DELAY_SECONDS = 300;

    public function __construct(
        private readonly EraTaskProgressGateway $taskProgressGateway,
        private readonly EraPageParser $pageParser = new EraPageParser(),
    ) {
    }

    public function type(): string
    {
        return 'parse_era_page';
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $html = $this->resolveSnapshotHtml($flow, $node);
        if ($html === '') {
            return new ActivityNodeResult(false, '缺少活动页快照 HTML', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $pageSnapshot = $this->pageParser->parse($html);
        if ($pageSnapshot === null) {
            return new ActivityNodeResult(false, 'ERA 页面解析失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        try {
            $progressSnapshot = $this->taskProgressGateway->fetchSnapshots(
                array_values(array_filter(array_map(
                    static fn ($task): string => $task instanceof \Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot ? $task->taskId() : '',
                    $pageSnapshot->tasks(),
                ))),
            );
        } catch (RequestException $exception) {
            return new ActivityNodeResult(false, 'ERA 页面解析成功，但任务进度同步失败: ' . $exception->getMessage(), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                'context_patch' => [
                    'era_page_snapshot' => $pageSnapshot->toArray(),
                ],
            ], $now);
        }

        $contextPatch = [
            'era_page_snapshot' => $pageSnapshot->toArray(),
        ];
        if ($progressSnapshot !== []) {
            $contextPatch['era_task_progress_snapshot'] = $progressSnapshot;
            $contextPatch['era_task_progress_fetched_at'] = $now;
        }

        return new ActivityNodeResult(true, 'ERA 页面解析成功', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $contextPatch,
        ], $now);
    }

    private function resolveSnapshotHtml(ActivityFlow $flow, ActivityNode $node): string
    {
        $fromNode = $node->payload()['activity_snapshot']['html'] ?? null;
        if (is_string($fromNode) && trim($fromNode) !== '') {
            return trim($fromNode);
        }

        $flowContext = $flow->context()->toArray();
        $fromContext = $flowContext['activity_snapshot']['html'] ?? null;
        if (is_string($fromContext) && trim($fromContext) !== '') {
            return trim($fromContext);
        }

        return '';
    }
}


