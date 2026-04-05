<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\Util\Resource\BaseResource;
use Bhp\Util\Resource\BaseResourcePoly;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResourceReloadTimestampTest extends TestCase
{
    private ?string $tempDir = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tempDir === null || !is_dir($this->tempDir)) {
            return;
        }

        $entries = scandir($this->tempDir);
        if (!is_array($entries)) {
            throw new RuntimeException('Failed to scan temp directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->tempDir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Failed to remove temp file: ' . $path);
            }
        }

        if (!rmdir($this->tempDir)) {
            throw new RuntimeException('Failed to remove temp directory: ' . $this->tempDir);
        }
    }

    public function testBaseResourceReloadsWhenOnlyMtimeChanges(): void
    {
        $file = $this->createResourceFile('version=1');
        $resource = new BaseResourceTestDouble($this->tempDir);
        $resource->boot(basename($file), 'ini');

        self::assertSame(1, $resource->get('version', 0, 'int'));

        $this->rewriteKeepingAtime($file, 'version=2');

        self::assertSame(2, $resource->get('version', 0, 'int'));
    }

    public function testBaseResourcePolyReloadsWhenOnlyMtimeChanges(): void
    {
        $file = $this->createResourceFile('version=1');
        $resource = new BaseResourcePolyTestDouble($this->tempDir);
        $resource->boot(basename($file), 'ini');

        self::assertSame(1, $resource->get('version', 0, 'int'));

        $this->rewriteKeepingAtime($file, 'version=2');

        self::assertSame(2, $resource->get('version', 0, 'int'));
    }

    private function createResourceFile(string $content): string
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bhp-resource-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->tempDir, 0777, true) || is_dir($this->tempDir));

        $file = $this->tempDir . DIRECTORY_SEPARATOR . 'resource.ini';
        self::assertNotFalse(file_put_contents($file, $content));

        return $file;
    }

    private function rewriteKeepingAtime(string $file, string $content): void
    {
        clearstatcache(true, $file);
        $atime = fileatime($file);
        $mtime = filemtime($file);

        self::assertIsInt($atime);
        self::assertIsInt($mtime);

        $nextMTime = max(time(), $mtime + 2);

        self::assertNotFalse(file_put_contents($file, $content));
        self::assertTrue(touch($file, $nextMTime, $atime));

        clearstatcache(true, $file);
        self::assertSame($atime, fileatime($file));
        self::assertSame($nextMTime, filemtime($file));
    }
}

final class BaseResourceTestDouble extends BaseResource
{
    public function __construct(private readonly string $baseDir)
    {
    }

    public function boot(string $filename, string $parser): void
    {
        $this->loadResource($filename, $parser);
    }

    protected function getFilePath(string $filename): string
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $filename;
    }
}

final class BaseResourcePolyTestDouble extends BaseResourcePoly
{
    public function __construct(private readonly string $baseDir)
    {
    }

    public function boot(string $filename, string $parser): void
    {
        $this->loadResource($filename, $parser);
    }

    protected function getFilePath(string $filename): string
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $filename;
    }
}
