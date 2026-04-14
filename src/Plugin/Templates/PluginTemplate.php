<?php declare(strict_types=1);

use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

class PluginTemplate extends BasePlugin implements PluginTaskInterface
{
    /**
     * 初始化 PluginTemplate
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    /**
     * 执行一次任务
     * @return TaskResult
     */
    public function runOnce(): TaskResult
    {
        return TaskResult::keepSchedule();
    }
}
