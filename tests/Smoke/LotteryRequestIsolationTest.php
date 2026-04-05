<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\App\ServiceContainer;
use Bhp\Cache\Cache;
use Bhp\Config\Config;
use Bhp\Device\Device;
use Bhp\FilterWords\FilterWords;
use Bhp\Http\HttpClient;
use Bhp\Http\HttpResponse;
use Bhp\Http\RequestOptions;
use Bhp\Log\Log;
use Bhp\Plugin\Builtin\Lottery\LotteryPlugin;
use Bhp\Profile\ProfileContext;
use Bhp\Request\Request;
use Bhp\Runtime\AppContext;
use Bhp\Runtime\Runtime;
use Bhp\Runtime\RuntimeContext;
use PHPUnit\Framework\TestCase;

final class LotteryRequestIsolationTest extends TestCase
{
    private ?Runtime $previousRuntime = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousRuntime instanceof Runtime) {
            Runtime::activate($this->previousRuntime);
        }
    }

    public function testFetchValidDynamicUrlSwallowsRequestExceptionAsWarningAndSkip(): void
    {
        $httpClient = new LotteryIsolationFailingHttpClient();
        ['log' => $log, 'context' => $context] = $this->activateRuntime($httpClient);

        $lottery = new TestableLotteryPlugin($context, $log);
        $lottery->setWaitCvList([123456]);

        $lottery->fetchValidDynamicUrlPublic('1905702375');

        self::assertTrue($log->hasWarningContaining('抽奖: 提取专栏失败'));
        self::assertSame([], $lottery->waitDynamicList());
    }

    /**
     * @return array{log: LotteryIsolationRecordingLog, context: AppContext}
     */
    private function activateRuntime(HttpClient $httpClient): array
    {
        $container = new ServiceContainer();
        $profileContext = ProfileContext::fromAppRoot(dirname(__DIR__, 2), 'lottery-request-isolation');
        $this->ensureProfileDirectories($profileContext);

        $config = $this->createStub(Config::class);
        $config
            ->method('get')
            ->willReturnCallback(static fn(string $key, mixed $default = null, string $type = 'default'): mixed => $default);

        $device = $this->createStub(Device::class);
        $device
            ->method('get')
            ->willReturnCallback(static function (string $key, mixed $default = null, string $type = 'default'): mixed {
                if ($key === 'platform.headers.pc_ua' || $key === 'platform.headers.other_ua') {
                    return 'phpunit-lottery-request-isolation-agent';
                }

                return $default;
            });

        $filterWords = $this->createStub(FilterWords::class);
        $filterWords
            ->method('get')
            ->willReturn([]);

        $context = new RuntimeContext($profileContext, $container);
        $log = new LotteryIsolationRecordingLog($context);

        $this->previousRuntime = Runtime::hasCurrent() ? Runtime::current() : null;
        Runtime::activate(new Runtime($container, $context));

        $cache = new Cache($profileContext);
        $container->setInstance(AppContext::class, $context);
        $container->setInstance(ProfileContext::class, $profileContext);
        $container->setInstance(Cache::class, $cache);
        $container->setInstance(Config::class, $config);
        $container->setInstance(Device::class, $device);
        $container->setInstance(FilterWords::class, $filterWords);
        $container->setInstance(Log::class, $log);
        $container->setInstance(HttpClient::class, $httpClient);
        $container->setInstance(Request::class, new Request($httpClient, $context, $cache, 0, 1, 0.1));

        return [
            'log' => $log,
            'context' => $context,
        ];
    }

    private function ensureProfileDirectories(ProfileContext $profileContext): void
    {
        foreach ([
            $profileContext->rootPath(),
            $profileContext->configPath(),
            $profileContext->logPath(),
            $profileContext->cachePath(),
        ] as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
    }
}

final class TestableLotteryPlugin extends LotteryPlugin
{
    public function __construct(AppContext $context, Log $log)
    {
        $this->assignBasePluginDependency('context', $context);
        $this->assignBasePluginDependency('log', $log);
    }

    /**
     * @param array<int, int> $cvList
     */
    public function setWaitCvList(array $cvList): void
    {
        $this->config['wait_cv_list'] = $cvList;
        $this->config['wait_dynamic_list'] = [];
        $this->config['dynamic_list'] = [];
    }

    public function fetchValidDynamicUrlPublic(string $uid): void
    {
        $this->fetchValidDynamicUrl($uid);
    }

    /**
     * @return array<int, int>
     */
    public function waitDynamicList(): array
    {
        return $this->config['wait_dynamic_list'];
    }

    private function assignBasePluginDependency(string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty(\Bhp\Plugin\BasePlugin::class, $property);
        $reflection->setValue($this, $value);
    }
}

final class LotteryIsolationRecordingLog extends Log
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

    public function hasWarningContaining(string $needle): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['level'] !== 'WARNING') {
                continue;
            }

            if (str_contains($entry['message'], $needle)) {
                return true;
            }
        }

        return false;
    }
}

final class LotteryIsolationFailingHttpClient extends HttpClient
{
    public function __construct()
    {
    }

    public function send(string $method, string $url, RequestOptions $options): HttpResponse
    {
        throw new \RuntimeException('lottery fetch timed out');
    }
}
