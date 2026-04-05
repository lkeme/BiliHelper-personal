<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class LogDependencyInjectionTest extends TestCase
{
    public function testLogSourceUsesInjectedAppContextInsteadOfRuntimeLookups(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Log/Log.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read Log source');
        self::assertStringContainsString('AppContext $context', $contents);
        self::assertStringContainsString('private static ?self $current = null;', $contents);
        self::assertStringNotContainsString('Runtime::service(Config::class)', $contents);
        self::assertStringNotContainsString('Runtime::context()', $contents);
        self::assertStringNotContainsString('Runtime::service(self::class)', $contents);
    }

    public function testAppKernelConstructsLogWithAppContext(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/App/AppKernel.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read AppKernel source');
        self::assertStringContainsString('new Log($services->get(AppContext::class))', $contents);
    }
}
