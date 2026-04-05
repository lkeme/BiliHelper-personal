<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class UserProfileServiceModernizationTest extends TestCase
{
    public function testSrcDoesNotReferenceLegacyUserFacade(): void
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if ($file->getPathname() === dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/User/UserProfileService.php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents, 'Failed to read ' . $file->getPathname());
            self::assertStringNotContainsString('use Bhp\\User\\User;', $contents, $file->getPathname());
            self::assertStringNotContainsString('User::isVip(', $contents, $file->getPathname());
            self::assertStringNotContainsString('User::isYearVip(', $contents, $file->getPathname());
        }
    }

    public function testLegacyUserFacadeFileIsRemoved(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/User/User.php');
    }
}
