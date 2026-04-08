<?php declare(strict_types=1);

namespace Bhp\Plugin;

final class PluginManifestValidator
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(array $manifest): array
    {
        $normalized = PluginManifest::fromArray($manifest)->toArray();

        if (array_key_exists('activity_url', $manifest)) {
            $normalized['activity_url'] = $manifest['activity_url'];
        } else {
            unset($normalized['activity_url']);
        }

        if (array_key_exists('reference_links', $manifest)) {
            $normalized['reference_links'] = $manifest['reference_links'];
        } else {
            unset($normalized['reference_links']);
        }

        return $normalized;
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

        $linkMetadataError = $this->validateLinkMetadata($hook, $manifest, $typedManifest);
        if ($linkMetadataError !== null) {
            return $linkMetadataError;
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

    /**
     * @param array<string, mixed>|PluginManifest $manifest
     */
    private function validateLinkMetadata(string $hook, array|PluginManifest $manifest, PluginManifest $typedManifest): ?string
    {
        if (!$typedManifest->activityUrlDeclared) {
            return "插件 {$hook} manifest 缺少关键字段 activity_url";
        }

        if (!$typedManifest->referenceLinksDeclared) {
            return "插件 {$hook} manifest 缺少关键字段 reference_links";
        }

        if ($manifest instanceof PluginManifest) {
            return $this->validateReferenceLinkItems($hook, $typedManifest->referenceLinks);
        }

        $activityUrl = $manifest['activity_url'] ?? null;
        if (!is_string($activityUrl)) {
            return "插件 {$hook} manifest.activity_url 必须为字符串";
        }

        $referenceLinks = $manifest['reference_links'] ?? null;
        if (!is_array($referenceLinks)) {
            return "插件 {$hook} manifest.reference_links 必须为数组";
        }

        return $this->validateReferenceLinkItems($hook, $referenceLinks);
    }

    /**
     * @param mixed $referenceLinks
     */
    private function validateReferenceLinkItems(string $hook, mixed $referenceLinks): ?string
    {
        if (!is_array($referenceLinks)) {
            return "插件 {$hook} manifest.reference_links 必须为数组";
        }

        foreach ($referenceLinks as $index => $item) {
            if (!is_array($item)) {
                return "插件 {$hook} manifest.reference_links[{$index}] 必须为对象";
            }

            if (!array_key_exists('url', $item)) {
                return "插件 {$hook} manifest.reference_links[{$index}] 缺少字段 url";
            }

            if (!array_key_exists('comment', $item)) {
                return "插件 {$hook} manifest.reference_links[{$index}] 缺少字段 comment";
            }

            if (!is_string($item['url'])) {
                return "插件 {$hook} manifest.reference_links[{$index}].url 必须为字符串";
            }

            if (!is_string($item['comment'])) {
                return "插件 {$hook} manifest.reference_links[{$index}].comment 必须为字符串";
            }

            if (trim($item['url']) === '') {
                return "插件 {$hook} manifest.reference_links[{$index}].url 不能为空";
            }
        }

        return null;
    }

    private function shortClassName(string $className): string
    {
        $normalized = str_replace('\\', '/', $className);

        return basename($normalized);
    }
}
