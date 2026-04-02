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
use Bhp\Console\Cli\RuntimeException as CliRuntimeException;
use Bhp\Console\Console;
use Bhp\Log\Log;
use Bhp\Plugin\Plugin;
use Bhp\Profile\ProfileCacheResetService;
use Bhp\Util\AsciiTable\AsciiTable;

final class ScriptCommand extends Command
{
    /**
     * @var string
     */
    protected string $desc = '[脚本模式] 使用一些额外功能脚本';

    /**
     *
     */
    public function __construct()
    {
        parent::__construct('mode:script', $this->desc);
        $this
            ->option('-l --list', '列出脚本插件')
            ->option('-p --plugin', '执行脚本插件')
            ->option('-P --plugins', '执行脚本插件列表，逗号分隔')
            ->option('-r --reset-cache', '执行前清理当前 profile 缓存（默认保留登录态）')
            ->option('--purge-auth', '清理缓存时同时清空登录态')
            ->usage(
                '<bold>  $0</end> <comment>user mode:script --list</end><eol/>' .
                '<bold>  $0</end> <comment>user mode:script --plugin ActivityInfoUpdate --file urls.txt</end><eol/>' .
                '<bold>  $0</end> <comment>user mode:script --plugin BatchUnfollow</end><eol/>' .
                '<bold>  $0</end> <comment>user m:s --plugin ActivityInfoUpdate --urls "url1,url2"</end><eol/>' .
                '<bold>  $0</end> <comment>m:s -P BatchUnfollow,ActivityInfoUpdate</end><eol/>' .
                '<bold>  $0</end> <comment>m:s --plugin ActivityInfoUpdate --reset-cache</end>'
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
            Log::info('脚本模式: 进入前清理缓存');
            (new ProfileCacheResetService())->reset((bool)($this->values()['purge-auth'] ?? false));
        }

        if ((bool)($this->values()['list'] ?? false)) {
            $this->renderScriptPluginList();
            return;
        }

        $single = trim((string)($this->values()['plugin'] ?? ''));
        $multi = trim((string)($this->values()['plugins'] ?? ''));
        if ($single === '' && $multi === '') {
            throw new CliRuntimeException('请通过 --list、--plugin 或 --plugins 指定脚本操作');
        }

        $selectedHooks = [];
        if ($single !== '') {
            $selectedHooks[] = $single;
        }
        if ($multi !== '') {
            $selectedHooks = array_merge($selectedHooks, preg_split('/[|,]/', $multi, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        }
        $selectedHooks = array_values(array_unique(array_map('trim', $selectedHooks)));
        $plugins = array_values(array_filter(
            Plugin::getPlugins(),
            static fn(array $plugin): bool => (($plugin['mode'] ?? 'app') === 'script')
                && in_array((string)($plugin['hook'] ?? ''), $selectedHooks, true)
        ));
        if ($plugins === []) {
            throw new CliRuntimeException('没有匹配到可执行脚本插件');
        }

        $argv = Console::getInstance()->argv();
        foreach ($plugins as $plugin) {
            Plugin::getInstance()->trigger((string)$plugin['hook'], $this->values(), $argv);
        }
    }

    protected function renderScriptPluginList(): void
    {
        $plugins = array_values(array_filter(
            Plugin::getPlugins(),
            static fn(array $plugin): bool => (($plugin['mode'] ?? 'app') === 'script')
        ));
        if ($plugins === []) {
            throw new CliRuntimeException('当前没有可用脚本插件');
        }

        $rows = array_map(static function (array $plugin): array {
            return [
                'name' => (string)($plugin['name'] ?? ''),
                'desc' => (string)($plugin['desc'] ?? ''),
                'priority' => (string)($plugin['priority'] ?? ''),
                'cycle' => (string)($plugin['cycle'] ?? ''),
            ];
        }, $plugins);

        foreach (AsciiTable::array2table($rows, '脚本插件列表') as $line) {
            echo $line . PHP_EOL;
        }
    }
}
