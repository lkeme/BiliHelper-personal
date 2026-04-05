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

    public function resourcesPath(): string
    {
        return self::normalizePath($this->appRoot . 'resources');
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
