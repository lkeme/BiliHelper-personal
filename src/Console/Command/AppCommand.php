<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Console\Command;

use Ahc\Cli\Input\Command;
use Ahc\Cli\IO\Interactor;
use Bhp\Log\Log;
use Bhp\Plugin\Plugin;
use Bhp\Task\Task;

class AppCommand extends Command
{
    /**
     * @var string
     */
    protected string $desc = '[主要模式] 默认功能';

    /**
     *
     */
    public function __construct()
    {
        parent::__construct('mode:app', $this->desc);
        //
        $this
            ->usage(
                '<bold>  $0</end> <comment>profile mode:app</end> ## 完整命令<eol/>' .
                '<bold>  $0</end> <comment>profile m:a</end> ## 完整命令(缩写)<eol/>' .
                '<bold>  $0</end> <comment>profile</end> ## 省略命令(保留profile命令)<eol/>' .
                '<bold>  $0</end> <comment>mode:app</end> ## 省略命令(保留动作命令)<eol/>' .
                '<bold>  $0</end> <comment>m:d</end> ## 省略命令(保留动作命令)(缩写)<eol/>'
            );
    }

    /**
     * @param Interactor $io
     * @return void
     */
    public function interact(Interactor $io): void
    {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        Log::info("执行 $this->desc");
        //
        $plugins = Plugin::getPlugins();
        foreach ($plugins as $plugin) {
            Task::addTask($plugin['hook'], null);
        }
        //
        Task::execTasks();
    }
}
