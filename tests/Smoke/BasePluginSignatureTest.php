<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BasePluginSignatureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function bootPluginTargets(): iterable
    {
        yield 'base plugin' => ['src/Plugin/BasePlugin.php'];
    }

    #[DataProvider('bootPluginTargets')]
    public function testBootPluginDoesNotRequirePassByReference(string $relativePath): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);
        self::assertStringNotContainsString('Plugin &$plugin', $contents, $relativePath);
        self::assertStringNotContainsString('Runtime::appContext()', $contents, $relativePath);
    }

    public function testCheckUpdatePluginUsesUnifiedBasePlugin(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins/CheckUpdate/src/CheckUpdatePlugin.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read CheckUpdate plugin source');
        self::assertStringNotContainsString('BasePluginRW', $contents);
        self::assertStringContainsString('extends BasePlugin', $contents);
    }

    public function testLoginPluginDoesNotNarrowBasePluginLogMethodVisibility(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Login/Login.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read Login plugin source');
        self::assertStringContainsString('extends BasePlugin', $contents);
        self::assertStringNotContainsString('private function warning(', $contents);
        self::assertStringNotContainsString('private function notice(', $contents);
        self::assertStringNotContainsString('private function info(', $contents);
    }

    public function testLegacyPluginRwBaseClassIsRemoved(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Plugin/BasePluginRW.php');
    }

    public function testPluginGuideDoesNotMentionLegacyPluginRwBaseClass(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'docs/PLUGIN_GUIDE.md';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read plugin guide');
        self::assertStringNotContainsString('BasePluginRW', $contents);
        self::assertStringNotContainsString('scheduleNextAt()', $contents);
    }
}
