<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class PluginModernizationRegressionTest extends TestCase
{
    public function testPluginEntrySourcesDoNotUseStaticLogOrRequestFacades(): void
    {
        $paths = [
            'src/Plugin/BasePlugin.php',
            'src/Plugin/Plugin.php',
        ];

        $builtinRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($builtinRoot));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $normalized = str_replace('\\', '/', $relativePath);
            if (!str_ends_with($normalized, 'Plugin.php') && !str_contains($normalized, '/Traits/')) {
                continue;
            }

            $paths[] = $normalized;
        }

        $patterns = [
            'Log::',
            'Request::csrf(',
            'Request::uid(',
            'Request::sid(',
            'Request::get(',
            'Request::post(',
            'Request::headers(',
            'Request::postJsonBody(',
            'Request::single(',
            '\\Bhp\\Request\\Request::csrf(',
            '\\Bhp\\Request\\Request::uid(',
            '\\Bhp\\Request\\Request::sid(',
            '\\Bhp\\Request\\Request::get(',
            '\\Bhp\\Request\\Request::post(',
            '\\Bhp\\Request\\Request::headers(',
            '\\Bhp\\Request\\Request::postJsonBody(',
            '\\Bhp\\Request\\Request::single(',
        ];

        foreach (array_values(array_unique($paths)) as $path) {
            $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $contents = file_get_contents($fullPath);

            self::assertIsString($contents, 'Failed to read ' . $path);
            foreach ($patterns as $pattern) {
                self::assertStringNotContainsString($pattern, $contents, $path);
            }
        }
    }

    public function testLiveGoldBoxAndLotteryPluginsDoNotUseLegacyFilterWordsSingleton(): void
    {
        foreach ([
            'plugins/LiveGoldBox/src/LiveGoldBoxPlugin.php',
            'plugins/Lottery/src/LotteryPlugin.php',
        ] as $path) {
            $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $path;
            $contents = file_get_contents($fullPath);

            self::assertIsString($contents, 'Failed to read ' . $path);
            self::assertStringNotContainsString('FilterWords::getInstance()', $contents, $path);
            self::assertStringContainsString('filterWords(', $contents, $path);
        }
    }

    public function testJudgePluginDoesNotUseStaticMethodsThatReferenceInstanceState(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins/Judge/src/JudgePlugin.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read JudgePlugin source');
        self::assertStringNotContainsString('private static function vote', $contents);
        self::assertStringNotContainsString('private static function judgementIndex', $contents);
    }
}
