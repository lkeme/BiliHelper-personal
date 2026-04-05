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

use Closure;
use LogicException;
use Bhp\Console\Cli\Command;
use Bhp\Console\Cli\Interactor;
use Bhp\Log\Log;
use Bhp\Plugin\Plugin;
use Bhp\Profile\ProfileCacheResetService;
use Bhp\Scheduler\Scheduler;
use Bhp\Util\AppTerminator;

final class DebugCommand extends Command
{
    /**
     * @var string
     */
    protected string $desc = '[Debug模式] 开发测试使用';

    /**
     *
     */
    public function __construct(
        private readonly Log $log,
        private readonly ?Closure $schedulerResolver = null,
        private readonly ?Closure $pluginResolver = null,
        private readonly ?Closure $cacheResetServiceResolver = null,
    ) {
        parent::__construct('mode:debug', $this->desc);
        //
        $this
            ->option('-p --plugin', '[默认会同时加载Login；无需登录的插件除外]测试插件')
            ->option('-P --plugins', '[默认会同时加载Login；无需登录的插件除外]测试插件列表')
            ->option('-r --reset-cache', '执行前清理当前 profile 缓存（默认保留登录态）')
            ->option('--purge-auth', '清理缓存时同时清空登录态')
            ->usage(
                '  $0 mode:debug --plugin TestPlugin' . PHP_EOL .
                '  $0 m:d -p TestPlugin' . PHP_EOL .
                '  $0 mode:debug --plugins TestPlugin,Test1Plugin' . PHP_EOL .
                '  $0 m:d -P TestPlugin,Test1Plugin' . PHP_EOL .
                '  $0 m:d -p TestPlugin --reset-cache' . PHP_EOL .
                '  $0 m:d -p TestPlugin --reset-cache --purge-auth'
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
        $this->log->recordInfo("执行 $this->desc");

        $single = trim((string)($this->values()['plugin'] ?? ''));
        $multi = trim((string)($this->values()['plugins'] ?? ''));
        if ($single === '' && $multi === '') {
            AppTerminator::fail('发生错误，未加载插件');
        }

        if ((bool)($this->values()['reset-cache'] ?? false) || (bool)($this->values()['purge-auth'] ?? false)) {
            $this->log->recordInfo('调试模式: 进入前清理缓存');
            $this->cacheResetService()->reset((bool)($this->values()['purge-auth'] ?? false));
        }
        $pp = [];
        if ($single !== '') {
            $pp[] = $single;
        }

        if ($multi !== '') {
            $pp = array_merge($pp, preg_split('/[|,]/', $multi, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        }

        $pp = array_values(array_unique(array_map('trim', $pp)));
        if (empty($pp)) {
            AppTerminator::fail('没有插件输入');
        }

        if (!in_array('Login', $pp, true) && $this->shouldAttachLogin($pp)) {
            array_unshift($pp, 'Login');
        }

        $selected = [];
        foreach ($this->plugin()->plugins() as $plugin) {
            if (($plugin['mode'] ?? 'app') === 'script') {
                continue;
            }
            if (in_array((string)$plugin['hook'], $pp, true)) {
                $selected[] = $plugin;
            }
        }

        if ($selected === []) {
            AppTerminator::fail('没有匹配到可执行插件');
        }

        $scheduler = $this->scheduler();
        $scheduler->registerPlugins($selected);
        $scheduler->run();
    }

    /**
     * @param string[] $hooks
     */
    private function shouldAttachLogin(array $hooks): bool
    {
        foreach ($this->plugin()->plugins() as $plugin) {
            $hook = (string)($plugin['hook'] ?? '');
            if ($hook === '' || !in_array($hook, $hooks, true)) {
                continue;
            }

            if (($plugin['requires_auth'] ?? true) !== false) {
                return true;
            }
        }

        return false;
    }

    private function scheduler(): Scheduler
    {
        $scheduler = $this->schedulerResolver instanceof Closure ? ($this->schedulerResolver)() : null;
        if ($scheduler instanceof Scheduler) {
            return $scheduler;
        }

        throw new LogicException('DebugCommand scheduler dependency is not configured.');
    }

    private function plugin(): Plugin
    {
        $plugin = $this->pluginResolver instanceof Closure ? ($this->pluginResolver)() : null;
        if ($plugin instanceof Plugin) {
            return $plugin;
        }

        throw new LogicException('DebugCommand plugin dependency is not configured.');
    }

    private function cacheResetService(): ProfileCacheResetService
    {
        $service = $this->cacheResetServiceResolver instanceof Closure ? ($this->cacheResetServiceResolver)() : null;
        if ($service instanceof ProfileCacheResetService) {
            return $service;
        }

        throw new LogicException('DebugCommand cache reset dependency is not configured.');
    }

}
