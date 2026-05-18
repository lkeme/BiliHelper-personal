<?php declare(strict_types=1);

namespace Bhp\Profile;

use RuntimeException;

final class ProfileRuntimeLock
{
    /**
     * @var resource|null
     */
    private mixed $handle = null;
    private string $owner = '';

    public function __construct(private readonly ProfileContext $profileContext)
    {
    }

    public function lockPath(): string
    {
        return $this->profileContext->cachePath() . 'profile.runtime.lock';
    }

    public function acquire(string $owner): void
    {
        if (is_resource($this->handle)) {
            return;
        }

        $lockPath = $this->lockPath();
        $directory = dirname($lockPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建 profile 运行锁目录: ' . $directory);
        }

        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('无法打开 profile 运行锁文件: ' . $lockPath);
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException(sprintf(
                '当前 profile "%s" 已有运行中的可变命令，请不要并发执行同一 profile。',
                $this->profileContext->name(),
            ));
        }

        $this->owner = $owner;
        $this->handle = $handle;
        $this->writeOwnerMetadata($handle, $owner);
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        try {
            flock($this->handle, LOCK_UN);
        } finally {
            fclose($this->handle);
            $this->handle = null;
            $this->owner = '';
        }
    }

    public function __destruct()
    {
        $this->release();
    }

    /**
     * @param resource $handle
     */
    private function writeOwnerMetadata(mixed $handle, string $owner): void
    {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'profile' => $this->profileContext->name(),
            'owner' => $owner,
            'pid' => getmypid() ?: 0,
            'started_at' => date(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        fflush($handle);
    }
}
