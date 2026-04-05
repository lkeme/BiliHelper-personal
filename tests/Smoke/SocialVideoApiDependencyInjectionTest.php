<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class SocialVideoApiDependencyInjectionTest extends TestCase
{
    public function testApiRelationUsesInjectedRequestServiceAndCallersDoNotUseStaticFacade(): void
    {
        $api = $this->readSource('src/Api/Api/X/Relation/ApiRelation.php');
        $batchUnfollow = $this->readSource('plugins/BatchUnfollow/src/BatchUnfollowPlugin.php');
        $followNode = $this->readSource('plugins/ActivityLottery/src/Internal/Node/EraFollowNodeRunner.php');
        $unfollowNode = $this->readSource('plugins/ActivityLottery/src/Internal/Node/EraUnfollowNodeRunner.php');

        self::assertStringContainsString('private readonly Request $request', $api);
        self::assertStringNotContainsString('public static function', $api);
        self::assertStringNotContainsString('Request::', $api);

        self::assertStringContainsString('new ApiRelation($this->appContext()->request())', $batchUnfollow);
        self::assertStringNotContainsString('ApiRelation::', $batchUnfollow);
        self::assertStringContainsString('ApiRelation $apiRelation', $followNode);
        self::assertStringContainsString('ApiRelation $apiRelation', $unfollowNode);
        self::assertStringNotContainsString('ApiRelation::', $followNode);
        self::assertStringNotContainsString('ApiRelation::', $unfollowNode);
    }

    public function testApiWatchUsesInjectedRequestServiceAndCallersDoNotUseStaticFacade(): void
    {
        $api = $this->readSource('src/Api/Video/ApiWatch.php');
        $watchService = $this->readSource('src/Automation/Watch/VideoWatchService.php');
        $mainSite = $this->readSource('plugins/MainSite/src/MainSitePlugin.php');
        $activityLotteryPlugin = $this->readSource('plugins/ActivityLottery/src/ActivityLotteryPlugin.php');

        self::assertStringContainsString('private readonly Request $request', $api);
        self::assertStringNotContainsString('public static function', $api);
        self::assertStringNotContainsString('Request::', $api);

        self::assertStringContainsString('ApiWatch $apiWatch', $watchService);
        self::assertStringNotContainsString('ApiWatch::', $watchService);
        self::assertStringContainsString('new ApiWatch($this->appContext()->request())', $mainSite);
        self::assertStringNotContainsString('ApiWatch::', $mainSite);
        self::assertStringContainsString('new VideoWatchService(', $activityLotteryPlugin);
        self::assertStringContainsString('new ApiWatch($this->appContext()->request())', $activityLotteryPlugin);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
