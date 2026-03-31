<?php declare(strict_types=1);

namespace Bhp\Plugin\Contract;

use Bhp\Scheduler\TaskResult;

interface PluginTaskInterface
{
    public function runOnce(): TaskResult;
}
