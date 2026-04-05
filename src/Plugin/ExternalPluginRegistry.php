<?php declare(strict_types=1);

namespace Bhp\Plugin;

use RuntimeException;

final class ExternalPluginRegistry
{
    /**
     * @return array<int, array{hook: string, name: string, class_name: string, path: string, source: string, vendor: string}>
     */
    public function all(string $appRoot): array
    {
        $pluginsRoot = rtrim(str_replace('\\', '/', $appRoot), '/') . '/plugins';
        if (!is_dir($pluginsRoot)) {
            return [];
        }

        $entries = [];
        foreach (glob($pluginsRoot . '/*/plugin.json') ?: [] as $manifestPath) {
            $entry = $this->parseManifest($manifestPath);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        usort($entries, static function (array $left, array $right): int {
            return [$left['vendor'], $left['hook']] <=> [$right['vendor'], $right['hook']];
        });

        return $entries;
    }

    /**
     * @return array{hook: string, name: string, class_name: string, path: string, source: string, vendor: string}|null
     */
    private function parseManifest(string $manifestPath): ?array
    {
        $raw = file_get_contents($manifestPath);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("插件 manifest 解析失败 {$manifestPath}: {$exception->getMessage()}", 0, $exception);
        }

        if (!is_array($decoded)) {
            return null;
        }

        $hook = trim((string)($decoded['hook'] ?? ''));
        $name = trim((string)($decoded['name'] ?? ''));
        $className = trim((string)($decoded['class_name'] ?? ''));
        $entry = trim((string)($decoded['entry'] ?? ''));
        $vendor = trim((string)($decoded['vendor'] ?? ''));
        $source = trim((string)($decoded['source'] ?? 'external'));
        if ($hook === '' || $name === '' || $className === '' || $entry === '') {
            return null;
        }

        $basePath = str_replace('\\', '/', dirname($manifestPath)) . '/';

        return [
            'hook' => $hook,
            'name' => $name,
            'class_name' => $className,
            'path' => $basePath . ltrim(str_replace('\\', '/', $entry), '/'),
            'source' => $source,
            'vendor' => $vendor !== '' ? $vendor : 'unknown',
        ];
    }
}
