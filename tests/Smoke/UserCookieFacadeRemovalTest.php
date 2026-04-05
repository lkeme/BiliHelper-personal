<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class UserCookieFacadeRemovalTest extends TestCase
{
    public function testRequestNoLongerExposesCookiePartsHelperAndAppContextOwnsCookieFields(): void
    {
        $request = $this->readSource('src/Request/Request.php');
        $context = $this->readSource('src/Runtime/AppContext.php');

        self::assertStringNotContainsString('public static function cookieParts(): array', $request);
        self::assertStringContainsString('public function csrf(): string', $context);
        self::assertStringContainsString('public function uid(): string', $context);
        self::assertStringContainsString('public function sid(): string', $context);
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/User/User.php');
    }

    public function testApiAndPluginSourcesDoNotReferenceUserParseCookie(): void
    {
        $paths = [
            'src/Api',
            'plugins/BpConsumption/src/BpConsumptionPlugin.php',
            'plugins/Lottery/src/LotteryPlugin.php',
            'plugins/ActivityInfoUpdate/src/Internal/ActivityInfoUpdateRunner.php',
        ];

        foreach ($paths as $relativePath) {
            $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
            if (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
                foreach ($iterator as $file) {
                    if (!$file->isFile() || $file->getExtension() !== 'php') {
                        continue;
                    }

                    $contents = file_get_contents($file->getPathname());
                    self::assertIsString($contents, 'Failed to read ' . $file->getPathname());
                    self::assertStringNotContainsString('User::parseCookie(', $contents, $file->getPathname());
                }
                continue;
            }

            $contents = file_get_contents($path);
            self::assertIsString($contents, 'Failed to read ' . $relativePath);
            self::assertStringNotContainsString('User::parseCookie(', $contents, $relativePath);
        }
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
