<?php declare(strict_types=1);

namespace Bhp\Profile;

use Bhp\Cache\Cache;
use Bhp\Runtime\AppContext;

final class ProfileCacheResetService
{
    public function __construct(
        private readonly AppContext $context,
        private readonly Cache $cache,
    ) {
    }

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

        $this->cache->flush();
        $this->removeFiles([
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3',
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3-shm',
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3-wal',
        ]);

        if ($authSnapshot !== []) {
            $this->context->restoreAuthSnapshot($authSnapshot);
            $this->context->log()->recordInfo('已保留当前登录态');
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
