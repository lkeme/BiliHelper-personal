<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class LegacyApiFacadeRemovalTest extends TestCase
{
    public function testLegacyApiClientsUseInjectedRequestService(): void
    {
        foreach ([
            'src/Api/Api/Pgc/Activity/Deliver/ApiTask.php',
            'src/Api/Api/Pgc/Activity/Score/ApiTask.php',
            'src/Api/Api/X/Activity/ApiActivity.php',
            'src/Api/Api/X/ActivityComponents/ApiMission.php',
            'src/Api/Api/X/Player/ApiPlayer.php',
            'src/Api/Api/X/Task/ApiTask.php',
            'src/Api/Api/X/VipPoint/ApiTask.php',
            'src/Api/Credit/ApiJury.php',
            'src/Api/Custom/ApiCalcSign.php',
            'src/Api/Dynamic/ApiDetail.php',
            'src/Api/Dynamic/ApiOpusSpace.php',
            'src/Api/Dynamic/ApiTopic.php',
            'src/Api/DynamicSvr/ApiDynamicSvr.php',
            'src/Api/Esports/ApiGuess.php',
            'src/Api/Lottery/V1/ApiAward.php',
            'src/Api/Passport/ApiCaptcha.php',
            'src/Api/Pay/ApiWallet.php',
            'src/Api/Room/V1/ApiArea.php',
            'src/Api/Room/V1/ApiDanMu.php',
            'src/Api/Room/V1/ApiInfo.php',
            'src/Api/Show/Api/Activity/Fire/Common/ApiEvent.php',
            'src/Api/Space/ApiArticle.php',
            'src/Api/Space/ApiReservation.php',
            'src/Api/Video/ApiLike.php',
            'src/Api/Vip/ApiPrivilege.php',
            'src/Api/XLive/ApiRevenueWallet.php',
            'src/Api/XLive/ApiXLiveSign.php',
            'src/Api/XLive/AppRoom/V1/ApiDM.php',
            'src/Api/XLive/AppUcenter/V1/ApiFansMedal.php',
            'src/Api/XLive/AppUcenter/V1/ApiUserTask.php',
            'src/Api/XLive/DataInterface/V1/HeartBeat/ApiHeartBeat.php',
            'src/Api/XLive/DataInterface/V1/X25Kn/ApiTrace.php',
            'src/Api/XLive/GeneralInterface/V1/ApiGuardBenefit.php',
            'src/Api/XLive/LotteryInterface/V1/ApiAnchor.php',
            'src/Api/XLive/LotteryInterface/V2/ApiBox.php',
            'src/Api/XLive/Revenue/V1/ApiWallet.php',
            'src/Api/XLive/WebInterface/V1/Second/ApiList.php',
            'src/Api/XLive/WebInterface/V1/WebMain/ApiRecommend.php',
            'src/Api/XLive/WebRoom/V1/Index/ApiIndex.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringContainsString('private readonly Request $request', $contents, $path);
            self::assertStringNotContainsString('public static function', $contents, $path);
            self::assertStringNotContainsString('Request::', $contents, $path);
            self::assertDoesNotMatchRegularExpression('/ApiJson::(?:get|post)\s*\(/', $contents, $path);
        }
    }

    public function testApiJsonIsDecodeOnlyUtility(): void
    {
        $contents = $this->readSource('src/Api/Support/ApiJson.php');

        self::assertStringNotContainsString('Request::', $contents);
        self::assertStringNotContainsString('public static function get(', $contents);
        self::assertStringNotContainsString('public static function post(', $contents);
        self::assertStringContainsString('public static function decode(string $raw, string $label): array', $contents);
    }

    public function testLegacyCallersUseExplicitApiInstancesInsteadOfStaticFacades(): void
    {
        $callers = [
            'src/Automation/Watch/LiveWatchService.php' => ['ApiIndex', 'ApiTrace'],
            'src/Login/LoginCaptchaService.php' => ['ApiCaptcha'],
            'plugins/ActivityInfoUpdate/src/Internal/ActivityInfoUpdateRunner.php' => ['ApiActivity'],
            'plugins/ActivityLottery/src/Internal/Gateway/DrawGateway.php' => ['ApiActivity'],
            'plugins/ActivityLottery/src/Internal/Gateway/EraTaskGateway.php' => ['ApiMission'],
            'plugins/ActivityLottery/src/Internal/Gateway/EraTaskProgressGateway.php' => ['ApiTask'],
            'plugins/ActivityLottery/src/Internal/Gateway/WatchLiveGateway.php' => ['ApiIndex', 'ApiList', 'ApiRecommend'],
            'plugins/ActivityLottery/src/Internal/Gateway/WatchVideoGateway.php' => ['ApiPlayer', 'ApiTopic'],
            'plugins/ActivityLottery/src/Internal/Node/EraShareNodeRunner.php' => ['ApiActivity'],
            'plugins/AwardRecords/src/AwardRecordsPlugin.php' => ['ApiWallet', 'ApiAward', 'ApiAnchor', 'ApiGuardBenefit'],
            'plugins/BpConsumption/src/BpConsumptionPlugin.php' => ['ApiWallet'],
            'plugins/DailyGold/src/DailyGoldPlugin.php' => ['ApiDM', 'ApiUserTask'],
            'plugins/GameForecast/src/GameForecastPlugin.php' => ['ApiGuess'],
            'plugins/Judge/src/JudgePlugin.php' => ['ApiJury'],
            'plugins/LiveGoldBox/src/LiveGoldBoxPlugin.php' => ['ApiBox'],
            'plugins/LiveReservation/src/LiveReservationPlugin.php' => ['ApiReservation'],
            'plugins/Lottery/src/LotteryDiscoveryService.php' => ['ApiArticle', 'ApiDetail'],
            'plugins/MainSite/src/MainSiteArchiveService.php' => ['ApiDynamicSvr', 'ApiPlayer'],
            'plugins/PolishMedal/src/PolishMedalPlugin.php' => ['ApiFansMedal'],
            'plugins/Silver2Coin/src/Silver2CoinPlugin.php' => ['ApiRevenueWallet'],
            'plugins/VipPoint/src/Traits/CommonTaskInfo.php' => ['ApiTask', 'ApiEvent'],
            'plugins/VipPoint/src/Traits/SignIn.php' => ['ApiTask', 'VipPointApiTask'],
            'plugins/VipPoint/src/VipPointPlugin.php' => ['ApiTask'],
        ];

        foreach ($callers as $path => $classNames) {
            $contents = $this->readSource($path);

            foreach ($classNames as $className) {
                self::assertStringNotContainsString($className . '::', $contents, $path . ' still uses ' . $className . '::');
                if (str_contains($path, 'VipPoint/Traits/CommonTaskInfo.php') || str_contains($path, 'VipPoint/src/Traits/CommonTaskInfo.php')) {
                    self::assertMatchesRegularExpression('/vipPoint(?:ScoreTask|DeliverTask|Event)Api\(/', $contents, $path);
                    continue;
                }

                if (str_contains($path, 'VipPoint/Traits/SignIn.php') || str_contains($path, 'VipPoint/src/Traits/SignIn.php')) {
                    self::assertMatchesRegularExpression('/vipPoint(?:ScoreTask|Task)Api\(/', $contents, $path);
                    continue;
                }

                self::assertMatchesRegularExpression(
                    '/(new\s+' . preg_quote($className, '/') . '\s*\(|' . preg_quote($className, '/') . '\s+\$[A-Za-z_][A-Za-z0-9_]*|function\s+[A-Za-z_][A-Za-z0-9_]*\s*\([^)]*\)\s*:\s*' . preg_quote($className, '/') . ')/',
                    $contents,
                    $path . ' does not expose an explicit ' . $className . ' dependency'
                );
            }
        }
    }

    public function testDirectApiJsonCallSitesAreRemoved(): void
    {
        foreach ([
            'src/Notice/Channel/BarkNoticeChannel.php',
            'plugins/CheckUpdate/src/CheckUpdatePlugin.php',
            'plugins/Lottery/src/LotteryPlugin.php',
            'plugins/Lottery/src/LotteryReservationExecutor.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertDoesNotMatchRegularExpression('/ApiJson::(?:get|post)\s*\(/', $contents, $path);
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
