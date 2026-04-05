<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class LoveClubMangaApiDependencyInjectionTest extends TestCase
{
    public function testLoveClubAndMangaApisUseInjectedRequestServiceAndPluginsUseInstances(): void
    {
        $loveClubApi = $this->readSource('src/Api/LinkGroup/ApiLoveClub.php');
        $mangaApi = $this->readSource('src/Api/Manga/ApiManga.php');
        $loveClubPlugin = $this->readSource('plugins/LoveClub/src/LoveClubPlugin.php');
        $mangaPlugin = $this->readSource('plugins/Manga/src/MangaPlugin.php');

        foreach ([$loveClubApi, $mangaApi] as $contents) {
            self::assertStringContainsString('private readonly Request $request', $contents);
            self::assertStringNotContainsString('public static function', $contents);
            self::assertStringNotContainsString('Request::', $contents);
        }

        self::assertStringContainsString('new ApiLoveClub($this->appContext()->request())', $loveClubPlugin);
        self::assertStringNotContainsString('ApiLoveClub::', $loveClubPlugin);
        self::assertStringContainsString('new ApiManga($this->appContext()->request())', $mangaPlugin);
        self::assertStringNotContainsString('ApiManga::', $mangaPlugin);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
