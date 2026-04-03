<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraPageParser;

final class ParseEraPageNodeRunner implements NodeRunnerInterface
{
    public function __construct(
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

        return new ActivityNodeResult(true, 'ERA 页面解析成功', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => [
                'era_page_snapshot' => $pageSnapshot->toArray(),
            ],
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

