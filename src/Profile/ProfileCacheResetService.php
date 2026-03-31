<?php declare(strict_types=1);

namespace Bhp\Profile;

use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Runtime\AppContext;
use Bhp\Util\Os\Path;

final class ProfileCacheResetService
{
    public function reset(bool $purgeAuth = false): void
    {
        $authSnapshot = $purgeAuth ? [] : (new AppContext())->authSnapshot();

        $this->clearCacheFiles($authSnapshot);
    }

    /**
     * @param array<string, string> $authSnapshot
     */
    private function clearCacheFiles(array $authSnapshot): void
    {
        $cacheDir = ProfileContext::fromRuntimeConstants()->cachePath();
        if (!is_dir($cacheDir)) {
            return;
        }

        Cache::clearAll();
        $this->removeFiles([
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3',
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3-shm',
            $cacheDir . DIRECTORY_SEPARATOR . 'cache.sqlite3-wal',
        ]);

        if ($authSnapshot !== []) {
            (new AppContext())->restoreAuthSnapshot($authSnapshot);
            Log::info('已保留当前登录态');
        }
    }

    /**
     * @param string[] $files
     */
    private function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                Log::info("清理缓存文件: " . basename($file));
            }
        }
    }
}
