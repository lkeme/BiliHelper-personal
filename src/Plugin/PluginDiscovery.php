<?php declare(strict_types=1);

namespace Bhp\Plugin;

final class PluginDiscovery
{
    private ?PluginClassNameResolver $classNameResolver = null;

    /**
     * @return array<int, array{name: string, class_name: string, path: string}>
     */
    public function discover(string $pluginPath): array
    {
        $plugins = [];
        foreach (scandir($pluginPath) ?: [] as $dirName) {
            if ($dirName === '.' || $dirName === '..') {
                continue;
            }

            if (in_array($dirName, ['Login', 'PluginTemplate', 'ScriptPluginTemplate'], true)) {
                continue;
            }

            if (!is_dir($pluginPath . $dirName)) {
                continue;
            }

            $directory = $pluginPath . $dirName . DIRECTORY_SEPARATOR;
            $entry = $this->resolveEntry($directory, $dirName);
            $path = $entry['path'] ?? ($directory . $dirName . '.php');
            if (!is_file($path)) {
                continue;
            }

            $plugins[] = [
                'name' => $dirName,
                'class_name' => $entry['class_name'] ?? $this->classNameResolver()->resolve($path, $dirName),
                'path' => $path,
            ];
        }

        return $plugins;
    }

    /**
     * @return array{path: string, class_name: string}|null
     */
    private function resolveEntry(string $directory, string $dirName): ?array
    {
        $conventionalPath = $directory . $dirName . '.php';
        if (is_file($conventionalPath)) {
            return [
                'path' => $conventionalPath,
                'class_name' => $this->classNameResolver()->resolve($conventionalPath, $dirName),
            ];
        }

        $classFiles = [];
        foreach (scandir($directory) ?: [] as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $path = $directory . $filename;
            if (!is_file($path) || strtolower((string)pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
                continue;
            }

            $className = $this->classNameResolver()->resolve($path, '');
            if ($className === '') {
                continue;
            }

            if ($this->shortClassName($className) === $dirName) {
                return [
                    'path' => $path,
                    'class_name' => $className,
                ];
            }

            $classFiles[] = [
                'path' => $path,
                'class_name' => $className,
            ];
        }

        if (count($classFiles) === 1) {
            return $classFiles[0];
        }

        return null;
    }

    private function shortClassName(string $className): string
    {
        $normalized = str_replace('\\', '/', $className);

        return basename($normalized);
    }

    private function classNameResolver(): PluginClassNameResolver
    {
        return $this->classNameResolver ??= new PluginClassNameResolver();
    }
}
