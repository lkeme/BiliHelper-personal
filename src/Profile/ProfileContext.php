<?php declare(strict_types=1);

namespace Bhp\Profile;

final class ProfileContext
{
    public function __construct(
        private readonly string $appRoot,
        private readonly string $name,
        private readonly string $rootPath,
        private readonly string $configPath,
        private readonly string $logPath,
        private readonly string $cachePath,
    ) {
    }

    public static function fromAppRoot(string $appRoot, string $name): self
    {
        $normalizedAppRoot = self::normalizeRoot($appRoot);
        $profileRoot = self::normalizePath($normalizedAppRoot . 'profile/' . $name);

        return new self(
            $normalizedAppRoot,
            $name,
            $profileRoot,
            self::normalizePath($profileRoot . 'config'),
            self::normalizePath($profileRoot . 'log'),
            self::normalizePath($profileRoot . 'cache'),
        );
    }

    public static function fromRuntimeConstants(?string $fallbackAppRoot = null, ?string $fallbackName = null): self
    {
        $appRoot = defined('APP_RESOURCES_PATH')
            ? dirname(rtrim(str_replace('\\', '/', (string)APP_RESOURCES_PATH), '/'))
            : ($fallbackAppRoot ?: (getcwd() ?: ''));

        if (defined('PROFILE_CONFIG_PATH')) {
            $configPath = self::normalizePath((string)PROFILE_CONFIG_PATH);
            $profileRoot = self::normalizePath(dirname(rtrim($configPath, '/')));
            $name = basename(rtrim($profileRoot, '/')) ?: ($fallbackName ?: 'user');

            if (basename(dirname(rtrim($profileRoot, '/'))) === 'profile') {
                $appRoot = dirname(dirname(rtrim($profileRoot, '/')));
            }

            return new self(
                self::normalizeRoot($appRoot),
                $name,
                $profileRoot,
                $configPath,
                defined('PROFILE_LOG_PATH') ? self::normalizePath((string)PROFILE_LOG_PATH) : self::normalizePath($profileRoot . 'log'),
                defined('PROFILE_CACHE_PATH') ? self::normalizePath((string)PROFILE_CACHE_PATH) : self::normalizePath($profileRoot . 'cache'),
            );
        }

        return self::fromAppRoot($appRoot, $fallbackName ?: 'user');
    }

    public function appRoot(): string
    {
        return $this->appRoot;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function configPath(): string
    {
        return $this->configPath;
    }

    public function logPath(): string
    {
        return $this->logPath;
    }

    public function cachePath(): string
    {
        return $this->cachePath;
    }

    public static function resolveResourcesPath(?string $fallbackAppRoot = null): string
    {
        if (defined('APP_RESOURCES_PATH')) {
            return self::normalizePath((string) APP_RESOURCES_PATH);
        }

        return self::normalizePath(self::normalizeRoot($fallbackAppRoot ?? (getcwd() ?: '')) . 'resources');
    }

    public static function resolvePluginPath(?string $fallbackAppRoot = null): string
    {
        if (defined('APP_PLUGIN_PATH')) {
            return self::normalizePath((string) APP_PLUGIN_PATH);
        }

        return self::normalizePath(self::normalizeRoot($fallbackAppRoot ?? (getcwd() ?: '')) . 'plugin');
    }

    private static function normalizeRoot(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }

    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }
}
