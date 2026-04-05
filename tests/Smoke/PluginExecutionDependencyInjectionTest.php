<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PluginExecutionDependencyInjectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function forbiddenHotPathLookups(): iterable
    {
        yield 'plugin runtime lookups' => [
            'src/Plugin/Plugin.php',
            [
                'Runtime::service(Config::class)',
                'Runtime::service(Console::class)',
                'Runtime::service(self::class)',
            ],
        ];

        yield 'scheduler plugin lookup' => [
            'src/Scheduler/Scheduler.php',
            [
                'Runtime::service(Plugin::class)',
            ],
        ];

        yield 'app command plugin statics' => [
            'src/Console/Command/AppCommand.php',
            [
                'Plugin::getPlugins(',
            ],
        ];

        yield 'debug command plugin statics' => [
            'src/Console/Command/DebugCommand.php',
            [
                'Plugin::getPlugins(',
            ],
        ];

        yield 'script command plugin statics and fallback construction' => [
            'src/Console/Command/ScriptCommand.php',
            [
                'Plugin::getPlugins(',
                'new Plugin(',
            ],
        ];
    }

    #[DataProvider('forbiddenHotPathLookups')]
    public function testPluginExecutionChainUsesInjectedCollaborators(string $relativePath, array $forbiddenSnippets): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);
        foreach ($forbiddenSnippets as $snippet) {
            self::assertStringNotContainsString($snippet, $contents, $relativePath . ' still contains ' . $snippet);
        }
    }
}
