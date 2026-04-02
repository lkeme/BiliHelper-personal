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
use Bhp\Profile\ProfileCacheResetService;
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
            ->option('-r --reset-cache', '执行前清理当前 profile 缓存（默认保留登录态）')
            ->option('-p --purge-auth', '清理缓存时同时清空登录态')
            ->usage(
                '  $0 profile mode:app  完整命令' . PHP_EOL .
                '  $0 profile m:a       完整命令(缩写)' . PHP_EOL .
                '  $0 profile           省略动作命令' . PHP_EOL .
                '  $0 mode:app          省略 profile 命令' . PHP_EOL .
                '  $0 m:a               省略 profile 命令(缩写)' . PHP_EOL .
                '  $0 m:a --reset-cache' . PHP_EOL .
                '  $0 m:a --reset-cache --purge-auth'
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
        if ((bool)($this->values()['reset-cache'] ?? false) || (bool)($this->values()['purge-auth'] ?? false)) {
            Log::info('主要模式: 进入前清理缓存');
            (new ProfileCacheResetService())->reset((bool)($this->values()['purge-auth'] ?? false));
        }

        $scheduler = Scheduler::getInstance();
        $scheduler->registerPlugins(array_values(array_filter(
            Plugin::getPlugins(),
            static fn(array $plugin): bool => (($plugin['mode'] ?? 'app') !== 'script')
        )));
        $scheduler->run();
    }
}
