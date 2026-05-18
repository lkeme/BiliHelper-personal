<?php declare(strict_types=1);

namespace Bhp\Profile;

use Bhp\Cache\Cache;
use Bhp\Runtime\AppContext;

final class ProfileCacheResetService
{
    /**
     * 初始化 ProfileCacheResetService
     * @param AppContext $context
     * @param Cache $cache
     */
    public function __construct(
        private readonly AppContext $context,
        private readonly Cache $cache,
    ) {
    }

    /**
     * 处理reset
     * @param bool $purgeAuth
     * @return void
     */
    public function reset(bool $purgeAuth = false): void
    {
        $authSnapshot = $purgeAuth ? [] : $this->context->authSnapshot();

        $this->clearCacheFiles($authSnapshot);
    }

    /**
     * @param array<string, string> $authSnapshot
     */
    private function clearCacheFiles(array $authSnapshot): void
    {
        $cacheDir = $this->context->cachePath();
        if (!is_dir($cacheDir)) {
            return;
        }

        $files = [
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3',
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3-shm',
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3-wal',
        ];
        $this->logResetPlan($files, $this->cache->scopes(), $authSnapshot !== []);

        $this->cache->flush();
        $this->removeFiles($files);

        if ($authSnapshot !== []) {
            $this->context->restoreAuthSnapshot($authSnapshot);
            $this->context->log()->recordInfo('已保留当前登录态');
        }
    }

    /**
     * @param string[] $files
     * @param string[] $scopes
     */
    private function logResetPlan(array $files, array $scopes, bool $keepAuth): void
    {
        $existingFiles = array_values(array_filter($files, 'is_file'));
        $this->context->log()->recordInfo(sprintf(
            '缓存重置: 登录态%s，持久 scope %d 个，缓存文件 %d 个',
            $keepAuth ? '保留' : '清理',
            count($scopes),
            count($existingFiles),
        ));

        if ($scopes !== []) {
            $this->context->log()->recordInfo('缓存重置 scope: ' . implode(', ', $scopes));
        }

        foreach ($existingFiles as $file) {
            $this->context->log()->recordInfo('缓存重置文件: ' . basename($file));
        }
    }

    /**
     * @param string[] $files
     */
    private function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $this->context->log()->recordInfo("清理缓存文件: " . basename($file));
            }
        }
    }
}
