<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\App\ServiceContainer;
use Bhp\Cache\Cache;
use Bhp\Config\Config;
use Bhp\Device\Device;
use Bhp\Http\HttpClient;
use Bhp\Http\HttpResponse;
use Bhp\Http\RequestOptions;
use Bhp\Log\Log;
use Bhp\Profile\ProfileContext;
use Bhp\Request\Request;
use Bhp\Runtime\AppContext;
use Bhp\Runtime\Runtime;
use Bhp\Runtime\RuntimeContext;
use Bhp\Util\Exceptions\RequestException;
use PHPUnit\Framework\TestCase;

final class RequestRetryBehaviorTest extends TestCase
{
    private ?Runtime $previousRuntime = null;
    private ?AppContext $activeContext = null;
    private ?HttpClient $activeHttpClient = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->previousRuntime instanceof Runtime) {
            Runtime::activate($this->previousRuntime);
        }
    }

    public function testHandleWithHttpClientExhaustionThrowsRequestExceptionAndCleansRequestCache(): void
    {
        $httpClient = new FailingHttpClient();
        $this->activateRuntime($httpClient);

        $request = new TestableRequest($httpClient, $this->requireContext(), $this->requireCache(), 0, 1, 0.1);
        $requestId = $request->prime('get', 'https://example.com');

        try {
            $request->perform($requestId);
            self::fail('Expected RequestException to be thrown');
        } catch (RequestException $exception) {
            self::assertSame('get', $exception->getMethod());
            self::assertSame('https://example.com', $exception->getUrl());
            self::assertSame(RequestException::CATEGORY_TIMEOUT, $exception->getCategory());
            self::assertSame($requestId, $exception->getRequestId());
            self::assertSame(2, $exception->getRetryCount());
            self::assertSame(2, $httpClient->attempts);
            self::assertSame(0, $request->requestCacheCount());
        }
    }

    public function testRequestExceptionCarriesRetryAndCategoryMetadata(): void
    {
        $exception = new RequestException(
            'network timeout',
            'GET',
            'https://example.com',
            28,
            null,
            RequestException::CATEGORY_TIMEOUT,
            'req-123',
            3,
        );

        self::assertSame('GET', $exception->getMethod());
        self::assertSame('https://example.com', $exception->getUrl());
        self::assertSame(RequestException::CATEGORY_TIMEOUT, $exception->getCategory());
        self::assertSame('req-123', $exception->getRequestId());
        self::assertSame(3, $exception->getRetryCount());
    }

    public function testPostJsonBodyUsesJsonRequestPayloadInsteadOfFormParams(): void
    {
        $httpClient = new RecordingRequestOptionsHttpClient();
        $this->activateRuntime($httpClient);

        $raw = Request::postJsonBody('other', 'https://example.com/json', ['hello' => 'world'], ['X-Test' => '1'], 1.5);

        self::assertSame('{"ok":true}', $raw);
        self::assertSame('post', $httpClient->method);
        self::assertSame('https://example.com/json', $httpClient->url);
        self::assertSame(['hello' => 'world'], $httpClient->options?->json);
        self::assertNull($httpClient->options?->formParams);
        self::assertSame('1', $httpClient->options?->headers['X-Test'] ?? null);
        self::assertSame(1.5, $httpClient->options?->timeout);
    }

    private function activateRuntime(HttpClient $httpClient): void
    {
        $container = new ServiceContainer();
        $profileContext = ProfileContext::fromAppRoot(dirname(__DIR__, 2), 'request-retry-test');
        $this->ensureProfileDirectories($profileContext);
        $config = $this->createStub(Config::class);
        $config
            ->method('get')
            ->willReturnCallback(static fn(string $key, mixed $default = null, string $type = 'default'): mixed => $default);
        $device = $this->createStub(Device::class);
        $device
            ->method('get')
            ->willReturnCallback(static function (string $key, mixed $default = null, string $type = 'default'): mixed {
                return match ($key) {
                    'platform.headers.other_ua' => 'phpunit-request-test-agent',
                    'platform.headers.app_ua' => 'phpunit-request-test-app-agent',
                    'platform.headers.pc_ua' => 'phpunit-request-test-pc-agent',
                    default => $default,
                };
            });

        $context = new RuntimeContext($profileContext, $container);
        $cache = new Cache($profileContext);

        $container->setInstance(ProfileContext::class, $profileContext);
        $container->setInstance(AppContext::class, $context);
        $container->setInstance(Cache::class, $cache);
        $container->setInstance(Config::class, $config);
        $container->setInstance(Device::class, $device);
        $container->setInstance(Log::class, new Log($context));
        $container->setInstance(HttpClient::class, $httpClient);
        $this->activeContext = $context;
        $this->activeHttpClient = $httpClient;

        $this->previousRuntime = Runtime::hasCurrent() ? Runtime::current() : null;
        Runtime::activate(new Runtime($container, $context));
        $container->setInstance(Request::class, new Request($httpClient, $context, $cache, 0, 0, 0.1));
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

    private function requireContext(): AppContext
    {
        self::assertInstanceOf(AppContext::class, $this->activeContext);

        return $this->activeContext;
    }

    private function requireCache(): Cache
    {
        $cache = $this->activeContext?->cache();
        self::assertInstanceOf(Cache::class, $cache);

        return $cache;
    }
}

final class TestableRequest extends Request
{
    public function prime(string $method, string $url): string
    {
        $requestId = $this->startRequest();
        $this->setRequest($requestId, 'url', $url);
        $this->setRequest($requestId, 'method', $method);
        $this->setRequest($requestId, 'options', []);

        return $requestId;
    }

    public function perform(string $requestId): HttpResponse
    {
        try {
            return $this->handleWithHttpClient($requestId);
        } finally {
            $this->stopRequest($requestId);
        }
    }

    public function requestCacheCount(): int
    {
        return count($this->caches);
    }
}

final class FailingHttpClient extends HttpClient
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

final class RecordingRequestOptionsHttpClient extends HttpClient
{
    public ?RequestOptions $options = null;
    public string $method = '';
    public string $url = '';

    public function __construct()
    {
    }

    public function send(string $method, string $url, RequestOptions $options): HttpResponse
    {
        $this->method = $method;
        $this->url = $url;
        $this->options = $options;

        return new HttpResponse(200, [], '{"ok":true}', 1.0, 'req-json');
    }
}
