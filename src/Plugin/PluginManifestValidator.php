<?php declare(strict_types=1);

namespace Bhp\Plugin;

final class PluginManifestValidator
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(array $manifest): array
    {
        return PluginManifest::fromArray($manifest)->toArray();
    }

    /**
     * @param array<string, mixed>|PluginManifest $manifest
     */
    public function validateManifest(string $hook, array|PluginManifest $manifest): ?string
    {
        $typedManifest = $this->toTypedManifest($manifest);
        $fillable = [
            'hook' => $typedManifest->hook,
            'name' => $typedManifest->name,
            'version' => $typedManifest->version,
            'desc' => $typedManifest->desc,
            'priority' => $typedManifest->priority,
            'cycle' => $typedManifest->cycle,
            'valid_until' => $typedManifest->validUntil,
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
        if (!in_array($typedManifest->hook, $allowedHooks, true)) {
            return "插件 {$hook} manifest.hook 必须与类名保持一致";
        }

        if (PluginManifest::parseManifestDateTime($typedManifest->validUntil) === null) {
            return "插件 {$hook} manifest.valid_until 必须为 Y-m-d H:i:s";
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
     * @param array<string, mixed>|PluginManifest $manifest
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
     * @param array<string, mixed>|PluginManifest $manifest
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
     * @param array<string, mixed>|PluginManifest $manifest
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

    private function shortClassName(string $className): string
    {
        $normalized = str_replace('\\', '/', $className);

        return basename($normalized);
    }
}
