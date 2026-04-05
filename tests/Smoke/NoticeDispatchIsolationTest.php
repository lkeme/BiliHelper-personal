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
use Bhp\Notice\Notice;
use Bhp\Profile\ProfileContext;
use Bhp\Request\Request;
use Bhp\Runtime\AppContext;
use Bhp\Runtime\Runtime;
use Bhp\Runtime\RuntimeContext;
use PHPUnit\Framework\TestCase;

final class NoticeDispatchIsolationTest extends TestCase
{
    private ?Runtime $previousRuntime = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousRuntime instanceof Runtime) {
            Runtime::activate($this->previousRuntime);
        }
    }

    public function testNoticePushSwallowsRequestExceptionFromLiveDispatchPath(): void
    {
        $httpClient = new NoticeDispatchFailingHttpClient();
        $log = $this->activateRuntime($httpClient);

        Notice::push('raffle', 'dispatch should not break the main flow');

        self::assertSame(2, $httpClient->attempts);
        self::assertTrue($log->hasWarningContaining('通知发送失败'));
        self::assertTrue($log->hasWarningContaining('timed out while connecting'));
    }

    public function testNoticeSourceUsesComposedMessageFactoryAndChannelList(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Notice/Notice.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read Notice source');
        self::assertStringContainsString('NoticeMessageFactory', $contents);
        self::assertStringContainsString('NoticeChannel', $contents);
        self::assertStringContainsString('private static ?self $current = null;', $contents);
        self::assertStringNotContainsString('Runtime::service(FilterWords::class)', $contents);
        self::assertStringNotContainsString('Runtime::service(Config::class)', $contents);
        self::assertStringNotContainsString('Runtime::service(self::class)', $contents);
    }

    public function testNoticeSourceDoesNotContainLegacyPerChannelDispatchMethods(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Notice/Notice.php';
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read Notice source');
        foreach ([
            'dingTalkSend(',
            'teleSend(',
            'scSend(',
            'sctSend(',
            'pushPlusSend(',
            'goCqhttp(',
            'debug(',
            'weCom(',
            'weComApp(',
            'feiShuSend(',
            'bark(',
            'pushDeer(',
        ] as $snippet) {
            self::assertStringNotContainsString($snippet, $contents);
        }
    }

    public function testSrcDoesNotReferenceStaticNoticePushFacade(): void
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents, 'Failed to read ' . $file->getPathname());
            self::assertStringNotContainsString('Notice::push(', $contents, $file->getPathname());
        }
    }

    public function testNoticeChannelSourcesDoNotUseStaticLogOrRequestFacades(): void
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src/Notice/Channel';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents, 'Failed to read ' . $file->getPathname());
            self::assertStringNotContainsString('Log::', $contents, $file->getPathname());
            self::assertStringNotContainsString('Request::', $contents, $file->getPathname());
        }
    }

    private function activateRuntime(HttpClient $httpClient): RecordingLog
    {
        $container = new ServiceContainer();
        $profileContext = ProfileContext::fromAppRoot(dirname(__DIR__, 2), 'notice-dispatch-isolation');
        $this->ensureProfileDirectories($profileContext);

        $configValues = [
            'notify.enable' => true,
            'notify_telegram.bottoken' => 'bot-token',
            'notify_telegram.chatid' => 'chat-id',
            'login_account.username' => 'notice-user',
            'log.enable' => false,
            'debug.enable' => false,
        ];

        $config = $this->createStub(Config::class);
        $config
            ->method('get')
            ->willReturnCallback(static function (string $key, mixed $default = null, string $type = 'default') use ($configValues): mixed {
                return array_key_exists($key, $configValues) ? $configValues[$key] : $default;
            });

        $device = $this->createStub(Device::class);
        $device
            ->method('get')
            ->willReturnCallback(static function (string $key, mixed $default = null, string $type = 'default'): mixed {
                if ($key === 'platform.headers.other_ua') {
                    return 'phpunit-notice-test-agent';
                }

                return $default;
            });

        $filterWords = $this->createStub(FilterWords::class);
        $filterWords
            ->method('get')
            ->willReturn([]);

        $context = new RuntimeContext($profileContext, $container);
        $log = new RecordingLog($context);

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
        $container->setInstance(Notice::class, new Notice($context, $filterWords));

        return $log;
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

final class RecordingLog extends Log
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

final class NoticeDispatchFailingHttpClient extends HttpClient
{
    public int $attempts = 0;

    public function __construct()
    {
    }

    public function send(string $method, string $url, RequestOptions $options): HttpResponse
    {
        $this->attempts++;

        throw new \RuntimeException('timed out while connecting');
    }
}
