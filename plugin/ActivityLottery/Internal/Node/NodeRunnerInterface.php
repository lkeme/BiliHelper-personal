<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;

interface NodeRunnerInterface
{
    public function type(): string;

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult;
}

