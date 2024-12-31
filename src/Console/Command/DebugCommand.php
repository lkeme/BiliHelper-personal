<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
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

final class DebugCommand extends Command
{
    /**
     * @var string
     */
    protected string $desc = '[Debug模式] 开发测试使用';

    /**
     *
     */
    public function __construct()
    {
        parent::__construct('mode:debug', $this->desc);
        //
        $this
            ->option('-p --plugin', '[默认会同时加载Login]测试插件')
            ->option('-P --plugins', '[默认会同时加载Login]测试插件列表')
            ->usage(
                '<bold>  $0</end> <comment>mode:debug --plugin TestPlugin</end> ## details 1<eol/>' .
                '<bold>  $0</end> <comment>m:d -p TestPlugin</end> ## details 2<eol/>' .
                '<bold>  $0</end> <comment>mode:debug --plugins TestPlugin|Test1Plugin</end> ## details 3<eol/>' .
                '<bold>  $0</end> <comment>m:d -P TestPlugin,Test1Plugin</end> ## details 4<eol/>'
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
        if (is_null($this->values()['plugin']) || $this->values()['plugins']) {
            failExit('发生错误，未加载插件');
        }
        //
        $p = $this->values()['plugin'];
        if (is_null($p)) {
            $temp = $this->values()['plugins'];
            $pp = explode(',', $temp);
        } else {
            $pp = [$p];
        }
        //
        if (empty($pp)) failExit('没有插件输入');
        array_unshift($pp, 'Login');
        //
        $plugins = Plugin::getPlugins();
        foreach ($plugins as $plugin) {
            if (!in_array($plugin['hook'], $pp)) {
                continue;
            }
            Task::addTask($plugin['hook'], null);
        }
        //
        Task::execTasks();
    }

}
