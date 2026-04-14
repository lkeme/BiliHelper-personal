<?php declare(strict_types=1);

namespace Bhp\Plugin\Contract;

use Bhp\Scheduler\TaskResult;

interface PluginTaskInterface
{
    /**
     * 执行一次任务
     * @return TaskResult
     */
    public function runOnce(): TaskResult;
}
