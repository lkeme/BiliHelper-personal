<?php declare(strict_types=1);

namespace Bhp\Plugin;

use ReflectionClass;

final class PluginManifestValidator
{
    /**
     * @return array<string, mixed>
     */
    public function readManifest(string $class): array
    {
        $typedManifest = $this->readTypedManifestCandidate($class);
        if ($typedManifest instanceof PluginManifest) {
            return $typedManifest->toArray();
        }

        if (method_exists($class, 'discoverManifest')) {
            $manifest = $class::discoverManifest();

            return is_array($manifest) ? $manifest : [];
        }

        $reflection = new ReflectionClass($class);
        $defaults = $reflection->getDefaultProperties();
        $manifest = $defaults['info'] ?? [];

        return is_array($manifest) ? $manifest : [];
    }

    public function readTypedManifest(string $class): PluginManifest
    {
        return $this->readTypedManifestCandidate($class) ?? PluginManifest::fromArray($this->readManifest($class));
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(array $manifest): array
    {
        return PluginManifest::fromArray($manifest)->toArray();
    }

    public function validateManifest(string $hook, array $manifest): ?string
    {
        return $this->validateTypedManifest($hook, PluginManifest::fromArray($manifest));
    }

    public function validateTypedManifest(string $hook, PluginManifest $manifest): ?string
    {
        $fillable = [
            'hook' => $manifest->hook,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'desc' => $manifest->desc,
            'priority' => $manifest->priority,
            'cycle' => $manifest->cycle,
        ];

        foreach ($fillable as $key => $value) {
            $missing = match ($key) {
                'priority' => $value === 0,
                default => $value === '',
            };

            if ($missing) {
                return "插件 {$hook} manifest 缺少关键字段 {$key}";
            }
        }

        $allowedHooks = [$hook, $this->shortClassName($hook)];
        if (!in_array($manifest->hook, $allowedHooks, true)) {
            return "插件 {$hook} manifest.hook 必须与类名保持一致";
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function coreCapabilities(): array
    {
        return [
            'core.app_context',
            'core.scheduler',
            'core.http',
            'core.notice',
            'core.log',
            'core.cache',
            'core.device',
            'core.auth',
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function validatePhpCompatibility(string $hook, array|PluginManifest $manifest): ?string
    {
        $typedManifest = $this->toTypedManifest($manifest);

        if ($typedManifest->phpMin !== '' && version_compare(PHP_VERSION, $typedManifest->phpMin, '<')) {
            return "插件 {$hook} 需要 PHP >= {$typedManifest->phpMin}，当前为 " . PHP_VERSION;
        }

        if ($typedManifest->phpMax !== null && version_compare(PHP_VERSION, $typedManifest->phpMax, '>')) {
            return "插件 {$hook} 需要 PHP <= {$typedManifest->phpMax}，当前为 " . PHP_VERSION;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function validateRequiredExtensions(string $hook, array|PluginManifest $manifest): ?string
    {
        $typedManifest = $this->toTypedManifest($manifest);
        $missing = [];
        foreach ($typedManifest->requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        if ($missing === []) {
            return null;
        }

        return "插件 {$hook} 缺少扩展: " . implode(', ', $missing);
    }

    /**
     * @param array<string, mixed> $manifest
     * @param string[] $availableCapabilities
     */
    public function validateRequiredCapabilities(string $hook, array|PluginManifest $manifest, array $availableCapabilities): ?string
    {
        $typedManifest = $this->toTypedManifest($manifest);
        $missing = [];
        foreach ($typedManifest->requiresCapabilities as $capability) {
            if (!in_array($capability, $availableCapabilities, true)) {
                $missing[] = $capability;
            }
        }

        if ($missing === []) {
            return null;
        }

        return "插件 {$hook} 缺少能力声明: " . implode(', ', $missing);
    }

    private function toTypedManifest(array|PluginManifest $manifest): PluginManifest
    {
        return $manifest instanceof PluginManifest ? $manifest : PluginManifest::fromArray($manifest);
    }

    private function readTypedManifestCandidate(string $class): ?PluginManifest
    {
        if (!method_exists($class, 'discoverManifest')) {
            return null;
        }

        $manifest = $class::discoverManifest();

        return $manifest instanceof PluginManifest ? $manifest : null;
    }

    private function shortClassName(string $className): string
    {
        $normalized = str_replace('\\', '/', $className);

        return basename($normalized);
    }
}
