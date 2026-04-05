<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\App\ServiceContainer;
use Bhp\Config\Config;
use Bhp\Log\Log;
use Bhp\Plugin\Builtin\MainSite\MainSiteArchiveService;
use Bhp\Plugin\Builtin\MainSite\MainSitePlugin;
use Bhp\Api\Video\ApiVideo;
use Bhp\Profile\ProfileContext;
use Bhp\Runtime\AppContext;
use Bhp\Runtime\Runtime;
use Bhp\Runtime\RuntimeContext;
use PHPUnit\Framework\TestCase;

final class MainSitePluginQualityTest extends TestCase
{
    public function testExtractArchiveAidReturnsEmptyStringWhenArchiveHasNoUsableAid(): void
    {
        $plugin = new TestableMainSitePlugin();

        self::assertSame('', $plugin->extractAid([]));
        self::assertSame('', $plugin->extractAid(['aid' => []]));
        self::assertSame('123', $plugin->extractAid(['aid' => 123]));
    }

    public function testCountCoinDeltaForLogIgnoresMalformedEntries(): void
    {
        $plugin = new TestableMainSitePlugin();
        $today = date('Y-m-d');

        self::assertSame(0, $plugin->countCoinDelta(null, $today));
        self::assertSame(0, $plugin->countCoinDelta([], $today));
        self::assertSame(0, $plugin->countCoinDelta(['time' => time(), 'reason' => '打赏', 'delta' => -1], $today));
        self::assertSame(0, $plugin->countCoinDelta(['time' => 'bad-time', 'reason' => '打赏', 'delta' => -1], $today));
        self::assertSame(0, $plugin->countCoinDelta(['time' => date('Y-m-d H:i:s'), 'reason' => [], 'delta' => -1], $today));
        self::assertSame(0, $plugin->countCoinDelta(['time' => date('Y-m-d H:i:s'), 'reason' => '打赏', 'delta' => '-1'], $today));
    }

    public function testCountCoinDeltaForLogCountsOnlyTodaysRewardEntries(): void
    {
        $plugin = new TestableMainSitePlugin();
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        self::assertSame(1, $plugin->countCoinDelta([
            'time' => $today . ' 08:00:00',
            'reason' => '视频打赏',
            'delta' => -1,
        ], $today));
        self::assertSame(2, $plugin->countCoinDelta([
            'time' => $today . ' 09:00:00',
            'reason' => '直播打赏',
            'delta' => -2,
        ], $today));
        self::assertSame(0, $plugin->countCoinDelta([
            'time' => $yesterday . ' 23:00:00',
            'reason' => '视频打赏',
            'delta' => -1,
        ], $today));
    }

    public function testExtractNewlistArchivesReturnsEmptyArrayForInvalidPayloads(): void
    {
        $plugin = new TestableMainSitePlugin();

        self::assertSame([], $plugin->extractNewlistArchivePayload(null));
        self::assertSame([], $plugin->extractNewlistArchivePayload(['code' => -1, 'data' => ['archives' => [['aid' => 1]]]]));
        self::assertSame([], $plugin->extractNewlistArchivePayload(['code' => 0, 'data' => []]));
        self::assertSame([], $plugin->extractNewlistArchivePayload(['code' => 0, 'data' => ['archives' => 'bad']]));
        self::assertSame([['aid' => 1]], $plugin->extractNewlistArchivePayload(['code' => 0, 'data' => ['archives' => [['aid' => 1]]]]));
    }

    public function testTopFeedArchivesRejectsNonZeroResponses(): void
    {
        $this->activateTestRuntime();

        $service = new class($this->createStub(Log::class), $this->createStub(ApiVideo::class)) extends MainSiteArchiveService {
            /**
             * @return array<string, mixed>
             */
            protected function fetchTopFeedRcmd(): array
            {
                return [
                    'code' => -1,
                    'message' => 'bad response',
                    'data' => [
                        'item' => [
                            ['id' => 1, 'title' => 'should be ignored'],
                        ],
                    ],
                ];
            }
        };

        self::assertSame([], $service->topFeedArchives(1));
    }

    public function testMainSitePluginSourceRemovesUnsafeDirectArchiveAidReads(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins/MainSite/src/MainSitePlugin.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read MainSitePlugin source');
        self::assertStringNotContainsString("(string) \$archive['aid']", $contents);
        self::assertStringContainsString('extractArchiveAid', $contents);
    }

    public function testMainSitePluginSourceBoundsRandomArchiveFetchRetries(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins/MainSite/src/MainSitePlugin.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read MainSitePlugin source');
        self::assertStringContainsString('MAX_NEWLIST_ATTEMPTS', $contents);
        self::assertStringContainsString('fetchVideoNewlist', $contents);
        self::assertStringNotContainsString('do {', $contents);
    }

    public function testMainSiteSourcesUseInjectedVideoApiClients(): void
    {
        $plugin = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins/MainSite/src/MainSitePlugin.php');
        $archiveService = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins/MainSite/src/MainSiteArchiveService.php');
        $apiVideo = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Api/Video/ApiVideo.php');
        $apiCoin = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Api/Video/ApiCoin.php');
        $apiShare = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Api/Video/ApiShare.php');

        self::assertIsString($plugin);
        self::assertIsString($archiveService);
        self::assertIsString($apiVideo);
        self::assertIsString($apiCoin);
        self::assertIsString($apiShare);

        self::assertStringContainsString('new ApiVideo($this->appContext()->request())', $plugin);
        self::assertStringContainsString('new ApiCoin($this->appContext()->request())', $plugin);
        self::assertStringContainsString('new ApiShare($this->appContext()->request())', $plugin);
        self::assertStringNotContainsString('ApiVideo::', $plugin);
        self::assertStringNotContainsString('ApiCoin::', $plugin);
        self::assertStringNotContainsString('ApiShare::', $plugin);

        self::assertStringContainsString('private readonly ApiVideo $apiVideo', $archiveService);
        self::assertStringNotContainsString('ApiVideo::', $archiveService);

        foreach ([$apiVideo, $apiCoin, $apiShare] as $contents) {
            self::assertStringContainsString('private readonly Request $request', $contents);
            self::assertStringNotContainsString('public static function', $contents);
            self::assertStringNotContainsString('Request::', $contents);
        }
    }

    private function activateTestRuntime(): void
    {
        $container = new ServiceContainer();
        $config = $this->createStub(Config::class);
        $config
            ->method('get')
            ->willReturnCallback(static fn(string $key, mixed $default = null, string $type = 'default'): mixed => $default);

        $profileContext = ProfileContext::fromAppRoot(dirname(__DIR__, 2), 'main-site-quality-test');
        $context = new RuntimeContext($profileContext, $container);

        $container->setInstance(Config::class, $config);
        $container->setInstance(AppContext::class, $context);
        $container->setInstance(Log::class, new Log($context));

        Runtime::activate(new Runtime($container, $context));
    }
}

final class TestableMainSitePlugin extends MainSitePlugin
{
    public function __construct()
    {
    }

    /**
     * @param array<string, mixed> $archive
     */
    public function extractAid(array $archive): string
    {
        return $this->extractArchiveAid($archive);
    }

    public function countCoinDelta(mixed $log, string $today): int
    {
        return $this->countCoinDeltaForLog($log, $today);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractNewlistArchivePayload(mixed $response): array
    {
        return $this->extractNewlistArchives($response);
    }
}
