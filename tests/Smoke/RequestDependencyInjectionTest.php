<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class RequestDependencyInjectionTest extends TestCase
{
    public function testRequestSourceUsesInjectedHttpClientAndAppContext(): void
    {
        $contents = $this->readSource('src/Request/Request.php');

        self::assertStringContainsString('HttpClient $httpClient', $contents);
        self::assertStringContainsString('AppContext $context', $contents);
        self::assertStringContainsString('private static ?self $current = null;', $contents);
        self::assertStringNotContainsString('Runtime::service(HttpClient::class)', $contents);
        self::assertStringNotContainsString('Runtime::service(self::class)', $contents);
        self::assertStringNotContainsString('return Runtime::appContext();', $contents);
    }

    public function testAppKernelConstructsRequestWithInjectedDependencies(): void
    {
        $contents = $this->readSource('src/App/AppKernel.php');

        self::assertStringContainsString('new Request(', $contents);
        self::assertStringContainsString('$services->get(HttpClient::class)', $contents);
        self::assertStringContainsString('$services->get(AppContext::class)', $contents);
    }

    public function testCacheSourceDoesNotUseRuntimeServiceLocator(): void
    {
        $contents = $this->readSource('src/Cache/Cache.php');

        self::assertStringNotContainsString('private static ?self $current = null;', $contents);
        self::assertStringNotContainsString('Runtime::service(self::class)', $contents);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
