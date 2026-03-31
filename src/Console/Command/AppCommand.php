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

use Bhp\Console\Cli\Command;
use Bhp\Console\Cli\Interactor;
use Bhp\Log\Log;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\Scheduler;

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
                '  $0 profile mode:app  完整命令' . PHP_EOL .
                '  $0 profile m:a       完整命令(缩写)' . PHP_EOL .
                '  $0 profile           省略动作命令' . PHP_EOL .
                '  $0 mode:app          省略 profile 命令' . PHP_EOL .
                '  $0 m:a               省略 profile 命令(缩写)'
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

        $scheduler = Scheduler::getInstance();
        $scheduler->registerPlugins(array_values(Plugin::getPlugins()));
        $scheduler->run();
    }
}
