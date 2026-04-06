<?php declare(strict_types=1);

namespace Bhp\Profile;

use InvalidArgumentException;

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
        $normalizedName = self::normalizeName($name);
        $profileBase = self::normalizePath($normalizedAppRoot . 'profile');
        $profileRoot = self::normalizePath($profileBase . $normalizedName);

        self::assertPathWithinBase($profileRoot, $profileBase, $normalizedName);

        return new self(
            $normalizedAppRoot,
            $normalizedName,
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

    private static function normalizeName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '') {
            throw new InvalidArgumentException('Profile name cannot be empty.');
        }

        if (preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9_-]{0,63})$/', $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf('Unsafe profile name "%s".', $name));
        }

        return $normalized;
    }

    private static function assertPathWithinBase(string $path, string $basePath, string $profileName): void
    {
        if (!str_starts_with($path, $basePath)) {
            throw new InvalidArgumentException(sprintf(
                'Profile "%s" resolves outside the profile root.',
                $profileName,
            ));
        }
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
