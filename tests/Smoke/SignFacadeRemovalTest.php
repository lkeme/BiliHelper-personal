<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class SignFacadeRemovalTest extends TestCase
{
    public function testLegacySignFacadeFileIsRemovedAndSrcDoesNotImportIt(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Sign/Sign.php');

        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents, 'Failed to read ' . $file->getPathname());
            self::assertStringNotContainsString('use Bhp\\Sign\\Sign;', $contents, $file->getPathname());
            self::assertDoesNotMatchRegularExpression('/(?<![A-Za-z0-9_\\\\])Sign::/', $contents, $file->getPathname());
        }
    }
}
