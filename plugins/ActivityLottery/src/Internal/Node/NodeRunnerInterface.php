<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;

interface NodeRunnerInterface
{
    /**
     * 获取类型标识
     * @return string
     */
    public function type(): string;

    /**
     * 启动执行流程
     * @param ActivityFlow $flow
     * @param ActivityNode $node
     * @param int $now
     * @return ActivityNodeResult
     */
    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult;
}


