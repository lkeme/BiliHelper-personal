<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\EraTaskProgressGateway;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageParser;

final class ParseEraPageNodeRunner implements NodeRunnerInterface
{
    public function __construct(
        private readonly EraPageParser $pageParser = new EraPageParser(),
        private readonly EraTaskProgressGateway $taskProgressGateway = new EraTaskProgressGateway(),
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

        $progressSnapshot = $this->taskProgressGateway->fetchSnapshots(
            array_values(array_filter(array_map(
                static fn ($task): string => $task instanceof \Bhp\Plugin\ActivityLottery\Internal\Page\EraTaskSnapshot ? $task->taskId() : '',
                $pageSnapshot->tasks(),
            ))),
        );

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

