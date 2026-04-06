<?php declare(strict_types=1);

namespace Bhp\Plugin;

final class ExternalPluginRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $appRoot): array
    {
        $pluginsRoot = rtrim(str_replace('\\', '/', $appRoot), '/') . '/plugins';
        if (!is_dir($pluginsRoot)) {
            return [];
        }

        $entries = [];
        foreach (glob($pluginsRoot . '/*/plugin.json') ?: [] as $manifestPath) {
            $entries[] = $this->parseManifest($manifestPath);
        }

        usort($entries, static function (array $left, array $right): int {
            return [$left['vendor'], $left['hook']] <=> [$right['vendor'], $right['hook']];
        });

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseManifest(string $manifestPath): array
    {
        $pluginDirectory = basename(dirname($manifestPath));
        $fallback = [
            'hook' => $pluginDirectory,
            'name' => $pluginDirectory,
            'class_name' => '',
            'path' => '',
            'autoload_root' => '',
            'namespace_prefix' => '',
            'source' => 'external',
            'vendor' => 'unknown',
            'manifest' => [],
            'manifest_error' => '',
        ];

        $raw = file_get_contents($manifestPath);
        if (!is_string($raw) || trim($raw) === '') {
            $fallback['manifest_error'] = "插件 manifest 为空: {$manifestPath}";

            return $fallback;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $fallback['manifest_error'] = "插件 manifest 解析失败 {$manifestPath}: {$exception->getMessage()}";

            return $fallback;
        }

        if (!is_array($decoded)) {
            $fallback['manifest_error'] = "插件 manifest 顶层结构非法: {$manifestPath}";

            return $fallback;
        }

        $hook = trim((string)($decoded['hook'] ?? ''));
        $name = trim((string)($decoded['name'] ?? ''));
        $className = trim((string)($decoded['class_name'] ?? ''));
        $entry = trim((string)($decoded['entry'] ?? ''));
        $vendor = trim((string)($decoded['vendor'] ?? ''));
        $source = trim((string)($decoded['source'] ?? 'external'));
        $basePath = str_replace('\\', '/', dirname($manifestPath)) . '/';

        $entryPath = $entry !== ''
            ? $basePath . ltrim(str_replace('\\', '/', $entry), '/')
            : '';

        return [
            'hook' => $hook !== '' ? $hook : $pluginDirectory,
            'name' => $name !== '' ? $name : ($hook !== '' ? $hook : $pluginDirectory),
            'class_name' => $className,
            'path' => $entryPath,
            'autoload_root' => $entryPath !== '' ? rtrim(str_replace('\\', '/', dirname($entryPath)), '/') . '/' : '',
            'namespace_prefix' => $this->namespacePrefix($className),
            'source' => $source !== '' ? $source : 'external',
            'vendor' => $vendor !== '' ? $vendor : 'unknown',
            'manifest' => $decoded,
            'manifest_error' => '',
        ];
    }

    private function namespacePrefix(string $className): string
    {
        $position = strrpos($className, '\\');
        if ($position === false) {
            return '';
        }

        return substr($className, 0, $position + 1);
    }
}
