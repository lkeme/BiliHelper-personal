<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class CacheDependencyInjectionTest extends TestCase
{
    public function testCacheSourceNoLongerExposesStaticFacadeState(): void
    {
        $contents = $this->readSource('src/Cache/Cache.php');

        self::assertStringNotContainsString('private static ?self $current', $contents);
        self::assertStringNotContainsString('public static function initCache', $contents);
        self::assertStringNotContainsString('public static function set(', $contents);
        self::assertStringNotContainsString('public static function get(', $contents);
        self::assertStringNotContainsString('public static function clearAll(', $contents);
    }

    public function testRequestAndProfileResetUseInjectedCacheService(): void
    {
        foreach ([
            'src/Request/Request.php',
            'src/Profile/ProfileCacheResetService.php',
            'src/Scheduler/SchedulerStateStore.php',
            'src/Login/LoginPendingFlowStore.php',
            'plugins/MainSite/src/MainSiteRecordStore.php',
            'plugins/Lottery/src/LotteryStateStore.php',
            'plugins/VipPoint/src/VipPointTaskStateStore.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringNotContainsString('Cache::', $contents, $path);
        }
    }

    public function testPluginSourcesDoNotUseStaticCacheFacade(): void
    {
        foreach ([
            'plugins/CheckUpdate/src/CheckUpdatePlugin.php',
            'plugins/ActivityLottery/src/ActivityLotteryPlugin.php',
            'plugins/AwardRecords/src/AwardRecordsPlugin.php',
            'plugins/LiveGoldBox/src/LiveGoldBoxPlugin.php',
            'plugins/VipPrivilege/src/VipPrivilegePlugin.php',
            'plugins/VipPoint/src/VipPointPlugin.php',
            'plugins/PolishMedal/src/PolishMedalPlugin.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringNotContainsString('Cache::', $contents, $path);
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
