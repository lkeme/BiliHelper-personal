<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class MangaSchedulingRegressionTest extends TestCase
{
    public function testMangaPluginTreatsDuplicateSignResponseAsCompletedState(): void
    {
        $contents = $this->readSource('plugins/Manga/src/MangaPlugin.php');

        self::assertStringContainsString("case 'invalid_argument':", $contents);
        self::assertStringContainsString('case 1:', $contents);
        self::assertStringContainsString("漫画: 今日已经签到过了哦~", $contents);
        self::assertStringNotContainsString("漫画: 签到失败 1 ->", $contents);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
