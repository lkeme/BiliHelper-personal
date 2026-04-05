<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\App\ServiceContainer;
use Bhp\Api\XLive\WebInterface\V1\Second\ApiList;
use Bhp\Api\XLive\WebInterface\V1\WebMain\ApiRecommend;
use Bhp\Api\XLive\WebRoom\V1\Index\ApiIndex;
use Bhp\Log\Log;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog\RemoteCatalogSource;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\ActivityLotteryGateway;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\WatchLiveGateway;
use Bhp\Profile\ProfileContext;
use Bhp\Runtime\AppContext;
use Bhp\Runtime\Runtime;
use Bhp\Runtime\RuntimeContext;
use Bhp\Util\Exceptions\RequestException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ActivityLotteryRequestIsolationTest extends TestCase
{
    private ?Runtime $previousRuntime = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousRuntime instanceof Runtime) {
            Runtime::activate($this->previousRuntime);
        }
    }

    public function testRemoteCatalogSourceReturnsEmptyArrayWhenFetcherThrowsRequestException(): void
    {
        $source = new RemoteCatalogSource(
            'https://example.test/activity_infos.json',
            true,
            null,
            static function (string $url): string {
                throw new RequestException(
                    'catalog fetch timed out',
                    'GET',
                    $url,
                    0,
                    null,
                    RequestException::CATEGORY_TIMEOUT,
                );
            }
        );

        self::assertSame([], $source->load());
    }

    public function testActivityLotteryGatewayReturnsEmptyStringWhenPageFetchThrowsRequestException(): void
    {
        $gateway = new ActivityLotteryGateway(
            static function (string $url): string {
                throw new RequestException(
                    'activity page timed out',
                    'GET',
                    $url,
                    0,
                    null,
                    RequestException::CATEGORY_TIMEOUT,
                );
            }
        );

        self::assertSame('', $gateway->fetchActivityPageHtml('https://example.test/activity'));
    }

    public function testWatchLiveAreaAccessIdFetchSwallowsRequestException(): void
    {
        $log = $this->activateRuntime();
        $apiList = (new \ReflectionClass(ApiList::class))->newInstanceWithoutConstructor();
        $apiRecommend = (new \ReflectionClass(ApiRecommend::class))->newInstanceWithoutConstructor();
        $apiIndex = (new \ReflectionClass(ApiIndex::class))->newInstanceWithoutConstructor();

        $gateway = new WatchLiveGateway(
            $apiList,
            $apiRecommend,
            $apiIndex,
            areaTagPageFetcher: static function (string $url): string {
                throw new RequestException(
                    'area tag fetch timed out',
                    'GET',
                    $url,
                    0,
                    null,
                    RequestException::CATEGORY_TIMEOUT,
                );
            },
            logger: static function (string $level, string $message, array $context = []) use ($log): void {
                match (strtolower($level)) {
                    'warning' => $log->recordWarning($message, $context),
                    'notice' => $log->recordNotice($message, $context),
                    'error' => $log->recordError($message, $context),
                    default => $log->recordDebug($message, $context),
                };
            },
        );

        $method = new ReflectionMethod(WatchLiveGateway::class, 'resolveAreaWebId');

        self::assertSame('', $method->invoke($gateway, 166, 6));
        self::assertTrue($log->hasDebugContaining('access_id 获取失败'));
        self::assertTrue($log->hasDebugContaining('area tag fetch timed out'));
    }

    public function testLiveWatchServiceDoesNotUseArrowFunctionForVoidRoomEntryAction(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Automation/Watch/LiveWatchService.php');

        self::assertIsString($contents, 'Failed to read LiveWatchService source');
        self::assertStringNotContainsString(': fn (int $roomId): void =>', $contents);
    }

    public function testParseEraPageNodeRunnerDoesNotDeclareOptionalConstructorParameterBeforeRequiredOne(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins/ActivityLottery/src/Internal/Node/ParseEraPageNodeRunner.php');

        self::assertIsString($contents, 'Failed to read ParseEraPageNodeRunner source');
        self::assertStringContainsString(
            "private readonly EraTaskProgressGateway \$taskProgressGateway,\n        private readonly EraPageParser \$pageParser = new EraPageParser(),",
            str_replace("\r\n", "\n", $contents)
        );
    }

    private function activateRuntime(): ActivityLotteryRecordingLog
    {
        $container = new ServiceContainer();
        $profileContext = ProfileContext::fromAppRoot(dirname(__DIR__, 2), 'activity-lottery-request-isolation');
        $context = new RuntimeContext($profileContext, $container);
        $log = new ActivityLotteryRecordingLog($context);

        $this->previousRuntime = Runtime::hasCurrent() ? Runtime::current() : null;
        Runtime::activate(new Runtime($container, $context));

        $container->setInstance(AppContext::class, $context);
        $container->setInstance(Log::class, $log);

        return $log;
    }
}

final class ActivityLotteryRecordingLog extends Log
{
    /**
     * @var array<int, array{level: string, message: string}>
     */
    private array $entries = [];

    protected function log(string $level, string $msg, array $context = []): void
    {
        $this->entries[] = [
            'level' => $level,
            'message' => $msg,
        ];
    }

    public function hasDebugContaining(string $needle): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['level'] !== 'DEBUG') {
                continue;
            }

            if (str_contains($entry['message'], $needle)) {
                return true;
            }
        }

        return false;
    }
}
