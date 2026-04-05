<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\Http\BurstRequest;
use Bhp\Http\HttpClient;
use Bhp\Http\HttpResponse;
use Bhp\Http\RaceRequest;
use Bhp\Http\RequestOptions;
use PHPUnit\Framework\TestCase;

final class HttpRequestWrapperDependencyInjectionTest extends TestCase
{
    public function testBurstRequestSourceDoesNotUseRuntimeAppContext(): void
    {
        $contents = $this->readSource('src/Http/BurstRequest.php');

        self::assertStringContainsString('HttpClient $httpClient', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
    }

    public function testRaceRequestSourceDoesNotUseRuntimeAppContext(): void
    {
        $contents = $this->readSource('src/Http/RaceRequest.php');

        self::assertStringContainsString('HttpClient $httpClient', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
    }

    public function testBurstRequestUsesInjectedHttpClientForConcurrentWave(): void
    {
        $httpClient = new RecordingWrapperHttpClient();
        $request = new BurstRequest($httpClient);

        $result = $request->runAt(
            microtime(true),
            [
                ['url' => 'https://example.com/a'],
                ['url' => 'https://example.com/b'],
            ],
            2,
            1,
            0.0,
        );

        self::assertSame(1, $httpClient->sendConcurrentCalls);
        self::assertCount(2, $httpClient->lastConcurrentRequests);
        self::assertCount(1, $result->waves());
    }

    public function testRaceRequestUsesInjectedHttpClientForWinnerSelection(): void
    {
        $httpClient = new RecordingWrapperHttpClient();
        $request = new RaceRequest($httpClient);

        $response = $request->run([
            ['url' => 'https://example.com/a'],
            ['url' => 'https://example.com/b'],
        ], 2);

        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(2, $httpClient->sendCalls);
        self::assertSame('https://example.com/a', $httpClient->sentUrls[0] ?? null);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}

final class RecordingWrapperHttpClient extends HttpClient
{
    public int $sendCalls = 0;
    public int $sendConcurrentCalls = 0;

    /**
     * @var list<string>
     */
    public array $sentUrls = [];

    /**
     * @var array<int, array{method?: string, url: string, options?: RequestOptions}>
     */
    public array $lastConcurrentRequests = [];

    public function __construct()
    {
    }

    public function send(string $method, string $url, RequestOptions $options): HttpResponse
    {
        $this->sendCalls++;
        $this->sentUrls[] = $url;

        return new HttpResponse(200, [], '{"ok":true}', 1.0, 'wrapper-send');
    }

    public function sendConcurrent(array $requests, int $concurrency = 5): array
    {
        $this->sendConcurrentCalls++;
        $this->lastConcurrentRequests = $requests;

        $responses = [];
        foreach ($requests as $key => $request) {
            $responses[$key] = new HttpResponse(200, [], '{"ok":true}', 1.0, 'wrapper-concurrent-' . $key);
        }

        return $responses;
    }
}
