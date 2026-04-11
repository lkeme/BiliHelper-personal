<?php declare(strict_types=1);

namespace Bhp\Plugin;

use Bhp\Config\Config;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Runtime\AppContext;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\AsciiTable\AsciiTable;
use DateTimeImmutable;
use DateTimeZone;
use ReflectionClass;
use RuntimeException;
use Stringable;
use Throwable;

class Plugin
{
    /**
     * @var array<string, array<string, array{0:object, 1:string}>>
     */
    protected array $_staff = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_plugins = [];

    /**
     * @var int[]
     */
    protected array $_priority = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_registry = [];

    /**
     * @var array<string, object>
     */
    protected array $_instances = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $pendingManifests = [];

    /**
     * @var array<string, string>
     */
    private array $pluginNamespaceRoots = [];

    private bool $pluginNamespaceAutoloaderRegistered = false;

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

    /**
     * @return array<string, mixed>
     */
    public function pluginDefinitionForClass(string $className): array
    {
        $hook = $this->resolveHookForClass($className);
        $plugin = $this->_plugins[$hook] ?? null;

        return is_array($plugin) ? $plugin : [];
    }

    public function trigger(string $hook, mixed ...$params): string
    {
        $pluginFuncResult = '';
        $handlers = $this->_staff[$hook] ?? [];
        if ($handlers === []) {
            return $pluginFuncResult;
        }

        if (!$this->canItRun($hook)) {
            return $pluginFuncResult;
        }

        foreach ($handlers as [$class, $method]) {
            if (!method_exists($class, $method)) {
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
        if (!$this->canItRun($hook)) {
            return TaskResult::keepSchedule();
        }

        if ($this->isPluginExpiredNow($hook)) {
            $this->markPluginExpired($hook, 'run');

            return TaskResult::after(3153600000.0, 'plugin expired');
        }

        $instance = $this->_instances[$hook] ?? null;
        if ($instance instanceof PluginTaskInterface) {
            return $instance->runOnce();
        }

        $this->trigger($hook);

        return TaskResult::keepSchedule();
    }

    public function register(object $classObject, string $method): void
    {
        $className = get_class($classObject);
        $hook = $this->resolveHookForClass($className);
        $funcClass = $hook . '->' . $method;

        $this->_staff[$hook][$funcClass] = [$classObject, $method];
        $this->_instances[$hook] = $classObject;

        if (!isset($this->_registry[$hook])) {
            $this->_registry[$hook] = [
                'hook' => $hook,
                'name' => $hook,
                'class_name' => $className,
                'path' => $this->resolveObjectPath($classObject),
                'source' => '',
                'vendor' => '',
                'status' => 'registered',
                'error' => '',
                'manifest' => [],
            ];
        }

        $manifest = $this->resolveManifestForHook($hook);
        if ($manifest === []) {
            throw new RuntimeException("插件 {$hook} 缺少 manifest 元数据");
        }

        if (!isset($this->_plugins[$hook])) {
            $this->addPluginInfo($hook, $manifest);
        }

        $this->_registry[$hook]['name'] = (string)($manifest['name'] ?? $hook);
        $this->_registry[$hook]['class_name'] = $className;
        $this->_registry[$hook]['path'] = $this->resolveObjectPath($classObject);
        $this->_registry[$hook]['source'] = (string)($manifest['source'] ?? ($this->_registry[$hook]['source'] ?? ''));
        $this->_registry[$hook]['vendor'] = (string)($manifest['vendor'] ?? ($this->_registry[$hook]['vendor'] ?? ''));
        $this->_registry[$hook]['status'] = 'registered';
        $this->_registry[$hook]['error'] = '';
        $this->_registry[$hook]['manifest'] = $manifest;
        unset($this->pendingManifests[$hook]);
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
        $required = ['hook', 'name', 'version', 'desc', 'priority', 'cycle'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $info)) {
                throw new RuntimeException("加载 {$hook} 插件错误，插件信息缺失字段 {$key}");
            }
        }

        if (array_key_exists($hook, $this->_plugins)) {
            throw new RuntimeException("加载 {$hook} 插件错误，插件名冲突");
        }

        if (in_array((int)$info['priority'], $this->_priority, true)) {
            throw new RuntimeException("加载 {$hook} 插件错误，插件优先级冲突");
        }

        if ((int)$info['priority'] < 1000) {
            throw new RuntimeException("加载 {$hook} 插件错误，插件优先级定义错误");
        }

        $info['status'] = $info['status'] ?? '√';

        return $info;
    }

    protected function detector(): void
    {
        $validator = new PluginManifestValidator();

        foreach ($this->getActivePlugins() as $plugin) {
            $hook = trim((string)($plugin['hook'] ?? $plugin['name'] ?? ''));
            if ($hook === '') {
                continue;
            }

            $manifest = is_array($plugin['manifest'] ?? null)
                ? $validator->normalize($plugin['manifest'])
                : [];
            $class = trim((string)($plugin['class_name'] ?? ($manifest['class_name'] ?? '')));
            $path = trim((string)($plugin['path'] ?? ''));
            $name = trim((string)($plugin['name'] ?? ($manifest['name'] ?? $hook)));

            $this->_registry[$hook] = [
                'hook' => $hook,
                'name' => $name !== '' ? $name : $hook,
                'class_name' => $class,
                'path' => $path,
                'source' => (string)($plugin['source'] ?? ''),
                'vendor' => (string)($plugin['vendor'] ?? ''),
                'status' => 'pending',
                'error' => '',
                'manifest' => $manifest,
            ];

            $manifestError = trim((string)($plugin['manifest_error'] ?? ''));
            if ($manifestError !== '') {
                $this->_registry[$hook]['status'] = 'failed';
                $this->_registry[$hook]['error'] = $manifestError;
                $this->log->recordWarning("插件 {$hook} 装配失败: {$manifestError}");
                continue;
            }

            $validationError = $validator->validateManifest($hook, $manifest)
                ?? $validator->validatePhpCompatibility($hook, $manifest)
                ?? $validator->validateRequiredExtensions($hook, $manifest);
            if ($validationError !== null) {
                $this->_registry[$hook]['status'] = 'failed';
                $this->_registry[$hook]['error'] = $validationError;
                $this->log->recordWarning("插件 {$hook} 装配失败: {$validationError}");
                continue;
            }

            if ($this->isManifestExpired($manifest)) {
                $message = $this->expiredMessage($manifest);
                $this->_registry[$hook]['status'] = 'expired';
                $this->_registry[$hook]['error'] = $message;
                $this->log->recordWarning("插件 {$hook} 已过期，跳过装配: {$message}");
                continue;
            }

            if (!$this->shouldLoadPluginForRuntimeMode($manifest)) {
                $this->_registry[$hook]['status'] = 'skipped';
                continue;
            }

            $this->registerPluginNamespaceAutoload($plugin);

            if ($path === '' || !is_file($path)) {
                $this->_registry[$hook]['status'] = 'missing';
                $this->_registry[$hook]['error'] = '插件入口文件不存在';
                continue;
            }

            if ($class === '' || !class_exists($class)) {
                $this->_registry[$hook]['status'] = 'failed';
                $this->_registry[$hook]['error'] = '插件类不存在';
                continue;
            }

            $this->pendingManifests[$hook] = $manifest;
            $this->_registry[$hook]['status'] = 'discovered';

            try {
                new $class($this);

                if (!isset($this->_staff[$hook])) {
                    throw new RuntimeException("插件 {$hook} 未完成注册");
                }
            } catch (Throwable $throwable) {
                unset($this->_staff[$hook], $this->_instances[$hook], $this->_plugins[$hook], $this->pendingManifests[$hook]);
                $this->_registry[$hook]['status'] = 'failed';
                $this->_registry[$hook]['error'] = $throwable->getMessage();
                $this->log->recordWarning("插件 {$hook} 装配失败: {$throwable->getMessage()}");
            }
        }

        $this->sortPlugins();
        $this->preloadPlugins();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getActivePlugins(): array
    {
        return array_merge(
            ($this->corePluginRegistry ?? new CorePluginRegistry())->all($this->appRoot),
            ($this->externalPluginRegistry ?? new ExternalPluginRegistry())->all($this->appRoot),
        );
    }

    protected function sortPlugins(string $columnKey = 'priority', int $sortOrder = SORT_ASC): void
    {
        uasort($this->_plugins, static function (array $left, array $right) use ($columnKey, $sortOrder): int {
            $result = (($left[$columnKey] ?? 0) <=> ($right[$columnKey] ?? 0));

            return $sortOrder === SORT_DESC ? -$result : $result;
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
                'enable' => $this->resolveEnableMark((string)($plugin['hook'] ?? '')),
            ];
        }, $visiblePlugins);

        foreach (AsciiTable::array2table($rows, '预加载插件列表') as $item) {
            echo $item . PHP_EOL;
        }
    }

    protected function canItRun(string $hook): bool
    {
        $plugin = $this->_plugins[$hook] ?? null;
        if (!is_array($plugin)) {
            return false;
        }

        if ($this->isPluginExpiredNow($hook)) {
            $this->markPluginExpired($hook, 'trigger');
            return false;
        }

        $start = trim((string)($plugin['start'] ?? ''));
        $end = trim((string)($plugin['end'] ?? ''));
        if ($start === '' || $end === '') {
            return true;
        }

        return $this->isWithinTimeRange($start, $end);
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

    private function isPluginExpiredNow(string $hook): bool
    {
        $manifest = $this->_registry[$hook]['manifest'] ?? null;

        return is_array($manifest) && $this->isManifestExpired($manifest);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function isManifestExpired(array $manifest): bool
    {
        $validUntil = PluginManifest::parseManifestDateTime(
            (string)($manifest['valid_until'] ?? ''),
            $this->manifestTimezone(),
        );
        if (!$validUntil instanceof DateTimeImmutable) {
            return false;
        }

        return $this->currentDateTime() > $validUntil;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function expiredMessage(array $manifest): string
    {
        return 'valid_until=' . (string)($manifest['valid_until'] ?? '');
    }

    private function markPluginExpired(string $hook, string $source): void
    {
        $status = (string)($this->_registry[$hook]['status'] ?? '');
        if ($status === 'expired') {
            return;
        }

        $manifest = $this->_registry[$hook]['manifest'] ?? [];
        $message = is_array($manifest) ? $this->expiredMessage($manifest) : '';
        if (isset($this->_registry[$hook])) {
            $this->_registry[$hook]['status'] = 'expired';
            $this->_registry[$hook]['error'] = $message;
        }
        if (isset($this->_plugins[$hook]) && is_array($this->_plugins[$hook])) {
            $this->_plugins[$hook]['status'] = 'expired';
        }

        $this->log->recordWarning("插件 {$hook} 已过期，跳过执行 [{$source}]: {$message}");
    }

    private function currentDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->manifestTimezone());
    }

    private function manifestTimezone(): DateTimeZone
    {
        return new DateTimeZone('Asia/Shanghai');
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

    protected function resolveEnableMark(string $hook): string
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

    private function resolveHookForClass(string $className): string
    {
        foreach ($this->_registry as $hook => $entry) {
            if (($entry['class_name'] ?? '') === $className) {
                return $hook;
            }
        }

        return $this->shortClassName($className);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveManifestForHook(string $hook): array
    {
        if (isset($this->pendingManifests[$hook])) {
            return $this->pendingManifests[$hook];
        }

        $manifest = $this->_registry[$hook]['manifest'] ?? [];

        return is_array($manifest) ? $manifest : [];
    }

    /**
     * @param array<string, mixed> $plugin
     */
    private function registerPluginNamespaceAutoload(array $plugin): void
    {
        $prefix = trim((string)($plugin['namespace_prefix'] ?? ''));
        $root = trim((string)($plugin['autoload_root'] ?? ''));
        if ($prefix === '' || $root === '') {
            return;
        }

        $normalizedPrefix = rtrim($prefix, '\\') . '\\';
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $this->pluginNamespaceRoots[$normalizedPrefix] = $normalizedRoot;

        if ($this->pluginNamespaceAutoloaderRegistered) {
            return;
        }

        spl_autoload_register(function (string $class): void {
            foreach ($this->pluginNamespaceRoots as $prefix => $root) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }

                $relative = substr($class, strlen($prefix));
                if ($relative === false || $relative === '') {
                    return;
                }

                $path = $root . str_replace('\\', '/', $relative) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }

                return;
            }
        });

        $this->pluginNamespaceAutoloaderRegistered = true;
    }
}
