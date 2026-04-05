<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class ProfileBootstrapDependencyInjectionTest extends TestCase
{
    public function testProfileCacheResetServiceUsesInjectedAppContext(): void
    {
        $contents = $this->readSource('src/Profile/ProfileCacheResetService.php');

        self::assertStringContainsString('AppContext $context', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
    }

    public function testStartupSelfCheckUsesInjectedAppContextAndBootstrapResolvesItFromContainer(): void
    {
        $startupSelfCheck = $this->readSource('src/Bootstrap/StartupSelfCheck.php');
        $bootstrap = $this->readSource('src/Bootstrap/Bootstrap.php');
        $appKernel = $this->readSource('src/App/AppKernel.php');

        self::assertStringContainsString('AppContext $context', $startupSelfCheck);
        self::assertStringNotContainsString('Runtime::appContext()', $startupSelfCheck);
        self::assertStringNotContainsString('new StartupSelfCheck()', $bootstrap);
        self::assertStringContainsString('get(StartupSelfCheck::class)', $bootstrap);
        self::assertStringContainsString('use Bhp\\Bootstrap\\StartupSelfCheck;', $appKernel);
        self::assertStringContainsString('set(StartupSelfCheck::class, static fn', $appKernel);
    }

    public function testConsoleCommandsDoNotManuallyConstructProfileCacheResetService(): void
    {
        foreach ([
            'src/Console/Command/AppCommand.php',
            'src/Console/Command/DebugCommand.php',
            'src/Console/Command/ScriptCommand.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringNotContainsString('new ProfileCacheResetService()', $contents, $path);
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
