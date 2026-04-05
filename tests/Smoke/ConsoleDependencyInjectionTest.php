<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsoleDependencyInjectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function runtimeLookupTargets(): iterable
    {
        yield 'console' => ['src/Console/Console.php'];
        yield 'app command' => ['src/Console/Command/AppCommand.php'];
        yield 'debug command' => ['src/Console/Command/DebugCommand.php'];
        yield 'script command' => ['src/Console/Command/ScriptCommand.php'];
    }

    #[DataProvider('runtimeLookupTargets')]
    public function testConsoleExecutionChainDoesNotUseRuntimeServiceLookups(string $relativePath): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);
        self::assertStringNotContainsString('Runtime::service(', $contents, $relativePath);
    }
}
