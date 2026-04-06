<?php declare(strict_types=1);

use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

class PluginTemplate extends BasePlugin implements PluginTaskInterface
{
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        return TaskResult::keepSchedule();
    }
}
