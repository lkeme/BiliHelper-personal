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

namespace Bhp\Plugin;

use Bhp\Config\Config;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Runtime\AppContext;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\AsciiTable\AsciiTable;
use Bhp\Util\AppTerminator;
use ReflectionClass;
use Stringable;
use Throwable;

class Plugin
{
    /**
     * 监听插件的启用/关闭|UUID下标
     * @var array<string, array<string, array{0:object,1:string}>>
     */
    protected array $_staff = [];

    /**
     * 保存所有插件信息
     * @var array<string, array<string, mixed>>
     */
    protected array $_plugins = [];

    /**
     * 保存插件优先级信息
     * @var int[]
     */
    protected array $_priority = [];

    /**
     * 插件注册表
     * @var array<string, array<string, mixed>>
     */
    protected array $_registry = [];

    /**
     * 插件实例
     * @var array<string, object>
     */
    protected array $_instances = [];

    public function __construct(
        private readonly Config $config,
        private readonly string $runtimeMode,
        private readonly string $appRoot,
        private readonly AppContext $context,
        private readonly Notice $notice,
        private readonly Log $log,
        private readonly ?CorePluginRegistry $corePluginRegistry = null,
        private readonly ?ExternalPluginRegistry $externalPluginRegistry = null,
    ) {
        $this->detector();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function plugins(): array
    {
        return $this->_plugins;
    }

    /**
     * @return int[]
     */
    public function priorities(): array
    {
        return $this->_priority;
    }

    /**
     * @return array<string, array<string, array{0:object,1:string}>>
     */
    public function staff(): array
    {
        return $this->_staff;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function registry(): array
    {
        return $this->_registry;
    }

    public function appContext(): AppContext
    {
        return $this->context;
    }

    public function notice(): Notice
    {
        return $this->notice;
    }

    public function log(): Log
    {
        return $this->log;
    }

    public function hasPlugin(string $hook): bool
    {
        return array_key_exists($hook, $this->_plugins);
    }

    public function trigger(string $hook, mixed ...$params): string
    {
        $pluginFuncResult = '';
        if (!isset($this->_staff[$hook]) || !is_array($this->_staff[$hook]) || $this->_staff[$hook] === []) {
            return $pluginFuncResult;
        }

        foreach ($this->_staff[$hook] as $staff) {
            $class = $staff[0];
            $method = $staff[1];
            if (!method_exists($class, $method)) {
                continue;
            }

            if (!$this->canItRun($class)) {
                continue;
            }

            $normalizedResult = $this->normalizeTriggerResult($class->$method(...$params));
            if ($normalizedResult === null) {
                continue;
            }

            $pluginFuncResult .= $normalizedResult;
        }

        return $pluginFuncResult;
    }

    public function runTask(string $hook): TaskResult
    {
        $instance = $this->_instances[$hook] ?? null;
        if ($instance instanceof PluginTaskInterface) {
            return $instance->runOnce();
        }

        $this->trigger($hook);

        return TaskResult::keepSchedule();
    }

    public function register(object $class_obj, string $method): void
    {
        $info = method_exists($class_obj, 'getPluginInfo') ? (array)$class_obj->getPluginInfo() : [];
        $hook = (string)($info['hook'] ?? $this->shortClassName(get_class($class_obj)));
        $func_class = $hook . '->' . $method;
        $this->_staff[$hook][$func_class] = [$class_obj, $method];
        $this->_instances[$hook] = $class_obj;

        if (!isset($this->_registry[$hook])) {
            $this->_registry[$hook] = [
                'hook' => $hook,
                'name' => (string)($info['name'] ?? $hook),
                'class_name' => get_class($class_obj),
                'path' => $this->resolveObjectPath($class_obj),
                'status' => 'registered',
                'error' => '',
            ];
        }

        if ($info !== []) {
            $this->addPluginInfo($hook, $info);
        }

        if (isset($this->_registry[$hook])) {
            $this->_registry[$hook]['status'] = 'registered';
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    protected function addPluginInfo(string $hook, array $info): void
    {
        $info = $this->validatePlugins($hook, $info);
        $this->_plugins[$hook] = $info;
        $this->_priority[] = (int)$info['priority'];
    }

    /**
     * @param array<string, mixed> $info
     * @return array<string, mixed>
     */
    protected function validatePlugins(string $hook, array $info): array
    {
        $fillable = ['hook', 'name', 'version', 'desc', 'priority', 'cycle'];
        foreach ($fillable as $val) {
            if (!array_key_exists($val, $info)) {
                AppTerminator::fail("加载 {$hook} 插件错误，插件信息缺失，请检查修正.");
            }
        }

        if (array_key_exists($hook, $this->_plugins)) {
            AppTerminator::fail("加载 {$hook} 插件错误，插件名冲突，请检查修正.");
        }

        if (in_array((int)$info['priority'], $this->_priority, true)) {
            AppTerminator::fail("加载 {$hook} 插件错误，插件优先级冲突，请检查修正.");
        }

        if ((int)$info['priority'] < 1000) {
            AppTerminator::fail("加载 {$hook} 插件错误，插件优先级定义错误，请检查修正.");
        }

        $info['status'] = $info['status'] ?? '√';

        return $info;
    }

    protected function detector(): void
    {
        $plugins = $this->getActivePlugins();
        foreach ($plugins as $plugin) {
            $hook = (string)($plugin['hook'] ?? $plugin['name'] ?? '');
            if ($hook === '') {
                continue;
            }

            $path = (string)($plugin['path'] ?? '');

            if ($path === '' || !is_file($path)) {
                $this->_registry[$hook] = [
                    'hook' => $hook,
                    'name' => $plugin['name'] ?? $hook,
                    'class_name' => $plugin['class_name'] ?? $hook,
                    'path' => $path,
                    'status' => 'missing',
                    'error' => '插件入口文件不存在',
                ];
                continue;
            }

            $this->_registry[$hook] = [
                'hook' => $hook,
                'name' => $plugin['name'] ?? $hook,
                'class_name' => $plugin['class_name'] ?? $hook,
                'path' => $path,
                'status' => 'pending',
                'error' => '',
            ];

            try {
                $class = (string)($plugin['class_name'] ?? $hook);
                if ($class === '' || !class_exists($class)) {
                    $this->_registry[$hook]['status'] = 'failed';
                    $this->_registry[$hook]['error'] = '插件类不存在';
                    continue;
                }

                $validator = new PluginManifestValidator();
                $manifest = $validator->readManifest($class);
                $manifestError = $validator->validateManifest($hook, $manifest)
                    ?? $validator->validatePhpCompatibility($hook, $manifest)
                    ?? $validator->validateRequiredExtensions($hook, $manifest);
                if ($manifestError !== null) {
                    $this->_registry[$hook]['status'] = 'failed';
                    $this->_registry[$hook]['error'] = $manifestError;
                    $this->log->recordWarning("插件 {$hook} 装配失败: {$manifestError}");
                    continue;
                }

                if (!$this->shouldLoadPluginForRuntimeMode($manifest)) {
                    $this->_registry[$hook]['status'] = 'skipped';
                    $this->_registry[$hook]['error'] = '';
                    continue;
                }

                $this->_registry[$hook]['status'] = 'discovered';
                $this->_registry[$hook]['error'] = '';

                new $class($this);
            } catch (Throwable $throwable) {
                $this->_registry[$hook]['status'] = 'failed';
                $this->_registry[$hook]['error'] = $throwable->getMessage();
                $this->log->recordWarning("插件 {$hook} 装配失败: {$throwable->getMessage()}");
            }
        }

        $this->sortPlugins();
        $this->preloadPlugins();
    }

    /**
     * @return array<int, array{hook: string, name: string, class_name: string, path: string}>
     */
    protected function getActivePlugins(): array
    {
        return array_merge(
            ($this->corePluginRegistry ?? new CorePluginRegistry())->all($this->appRoot),
            ($this->externalPluginRegistry ?? new ExternalPluginRegistry())->all($this->appRoot),
        );
    }

    protected function sortPlugins(string $column_key = 'priority', int $sort_order = SORT_ASC): void
    {
        uasort($this->_plugins, static function (array $left, array $right) use ($column_key, $sort_order): int {
            $result = (($left[$column_key] ?? 0) <=> ($right[$column_key] ?? 0));

            return $sort_order === SORT_DESC ? -$result : $result;
        });
    }

    protected function preloadPlugins(): void
    {
        if (!in_array($this->runtimeMode(), ['app', 'debug'], true)) {
            return;
        }

        $visiblePlugins = array_values(array_filter(
            $this->_plugins,
            static fn(array $plugin): bool => (($plugin['mode'] ?? 'app') !== 'script')
        ));
        if ($visiblePlugins === []) {
            return;
        }
        $rows = array_map(function (array $plugin): array {
            return [
                'name' => (string)($plugin['name'] ?? ''),
                'desc' => (string)($plugin['desc'] ?? ''),
                'priority' => (string)($plugin['priority'] ?? ''),
                'cycle' => (string)($plugin['cycle'] ?? ''),
                'start' => (string)($plugin['start'] ?? ''),
                'end' => (string)($plugin['end'] ?? ''),
                'enable' => $this->resolveEnableMark((string)($plugin['hook'] ?? ''), $plugin),
            ];
        }, $visiblePlugins);

        $th_list = AsciiTable::array2table($rows, '预加载插件列表');
        foreach ($th_list as $item) {
            echo $item . PHP_EOL;
        }
    }

    protected function canItRun(mixed $class): bool
    {
        if (!isset($class->info['start']) || !isset($class->info['end'])) {
            return true;
        }

        return $this->isWithinTimeRange((string)$class->info['start'], (string)$class->info['end']);
    }

    protected function shortClassName(string $className): string
    {
        $normalized = str_replace('\\', '/', $className);

        return basename($normalized);
    }

    protected function isWithinTimeRange(string $start, string $end): bool
    {
        $nowTime = $this->currentTimestamp();
        $startTime = $this->parseTimeWindowBoundary($start, $nowTime);
        $endTime = $this->parseTimeWindowBoundary($end, $nowTime);
        if ($startTime === null || $endTime === null) {
            return false;
        }

        if ($endTime < $startTime) {
            if ($nowTime < $startTime) {
                $startTime = strtotime('-1 day', $startTime);
            } else {
                $endTime = strtotime('+1 day', $endTime);
            }
        }

        return $nowTime >= $startTime && $nowTime <= $endTime;
    }

    protected function currentTimestamp(): int
    {
        return time();
    }

    protected function parseTimeWindowBoundary(string $time, int $referenceTimestamp): ?int
    {
        $normalized = trim($time);
        if (!preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $normalized)) {
            return null;
        }

        $boundary = strtotime(date('Y-m-d', $referenceTimestamp) . ' ' . $normalized);
        return $boundary === false ? null : $boundary;
    }

    protected function normalizeTriggerResult(mixed $result): ?string
    {
        return match (true) {
            is_int($result), is_float($result), is_string($result) => (string)$result,
            $result instanceof Stringable => (string)$result,
            default => null,
        };
    }

    protected function resolveObjectPath(object $object): string
    {
        try {
            $reflection = new ReflectionClass($object);
            return (string)($reflection->getFileName() ?: '');
        } catch (\ReflectionException) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $plugin
     */
    protected function resolveEnableMark(string $hook, array $plugin): string
    {
        foreach ($this->configKeyCandidates($hook) as $key) {
            $enabled = $this->config->get($key . '.enable', null);
            if ($enabled === null) {
                continue;
            }

            return $this->config->get($key . '.enable', false, 'bool') ? '●' : '○';
        }

        return '◉';
    }

    /**
     * @return string[]
     */
    protected function configKeyCandidates(string $hook): array
    {
        $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $hook));
        $compact = str_replace('_', '', $snake);

        return array_values(array_unique([$snake, $compact]));
    }

    /**
     * @param array<string, mixed> $manifest
     */
    protected function shouldLoadPluginForRuntimeMode(array $manifest): bool
    {
        $pluginMode = (string)($manifest['mode'] ?? 'app');
        $runtimeMode = $this->runtimeMode();

        return match ($runtimeMode) {
            'script' => $pluginMode === 'script',
            'app', 'debug' => $pluginMode !== 'script',
            'restore' => false,
            default => $pluginMode !== 'script',
        };
    }

    protected function runtimeMode(): string
    {
        return $this->runtimeMode;
    }
}
