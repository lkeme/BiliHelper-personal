<?php declare(strict_types=1);

namespace Bhp\Profile;

use InvalidArgumentException;

final class ProfileContext
{
    /**
     * 初始化 ProfileContext
     * @param string $appRoot
     * @param string $name
     * @param string $rootPath
     * @param string $configPath
     * @param string $logPath
     * @param string $cachePath
     */
    public function __construct(
        private readonly string $appRoot,
        private readonly string $name,
        private readonly string $rootPath,
        private readonly string $configPath,
        private readonly string $logPath,
        private readonly string $cachePath,
    ) {
    }

    /**
     * 处理from应用Root
     * @param string $appRoot
     * @param string $name
     * @return self
     */
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

    /**
     * 处理应用Root
     * @return string
     */
    public function appRoot(): string
    {
        return $this->appRoot;
    }

    /**
     * 处理名称
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * 处理rootPath
     * @return string
     */
    public function rootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * 处理配置Path
     * @return string
     */
    public function configPath(): string
    {
        return $this->configPath;
    }

    /**
     * 处理日志Path
     * @return string
     */
    public function logPath(): string
    {
        return $this->logPath;
    }

    /**
     * 处理缓存Path
     * @return string
     */
    public function cachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * 处理resourcesPath
     * @return string
     */
    public function resourcesPath(): string
    {
        return self::normalizePath($this->appRoot . 'resources');
    }

    /**
     * 标准化名称
     * @param string $name
     * @return string
     */
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

    /**
     * 断言PathWithinBase
     * @param string $path
     * @param string $basePath
     * @param string $profileName
     * @return void
     */
    private static function assertPathWithinBase(string $path, string $basePath, string $profileName): void
    {
        if (!str_starts_with($path, $basePath)) {
            throw new InvalidArgumentException(sprintf(
                'Profile "%s" resolves outside the profile root.',
                $profileName,
            ));
        }
    }

    /**
     * 标准化Root
     * @param string $path
     * @return string
     */
    private static function normalizeRoot(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }

    /**
     * 标准化Path
     * @param string $path
     * @return string
     */
    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/') . '/';
    }
}
