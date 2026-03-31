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
use Bhp\Profile\ProfileCacheResetService;

final class RestoreCommand extends Command
{
    /**
     * @var string
     */
    protected string $desc = '[复位模式] 复位一些缓存以及设置';

    /**
     *
     */
    public function __construct()
    {
        parent::__construct('mode:restore', $this->desc);
        $this
            ->option('-p --purge-auth', '同时清空登录态凭据')
            ->usage(
                '  $0 mode:restore' . PHP_EOL .
                '  $0 m:r' . PHP_EOL .
                '  $0 m:r --purge-auth'
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
        (new ProfileCacheResetService())->reset((bool)($this->values()['purge-auth'] ?? false));
    }
}
