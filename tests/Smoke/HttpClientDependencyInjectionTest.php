<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class HttpClientDependencyInjectionTest extends TestCase
{
    public function testGovernanceInterceptorProviderUsesInjectedAppContext(): void
    {
        $contents = $this->readSource('src/Http/HttpRequestGovernanceInterceptorProvider.php');

        self::assertStringContainsString('AppContext $context', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
    }

    public function testInterceptorRegistryIsNoLongerSingleton(): void
    {
        $contents = $this->readSource('src/Http/HttpClientInterceptorRegistry.php');

        self::assertStringNotContainsString('private static ?self $instance', $contents);
        self::assertStringNotContainsString('getInstance()', $contents);
    }

    public function testHttpClientUsesInjectedAppContextAndInterceptorRegistry(): void
    {
        $contents = $this->readSource('src/Http/HttpClient.php');

        self::assertStringContainsString('AppContext $context', $contents);
        self::assertStringContainsString('HttpClientInterceptorRegistry $interceptorRegistry', $contents);
        self::assertStringNotContainsString('HttpClientInterceptorRegistry::getInstance()', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
    }

    public function testAppKernelConstructsHttpClientDependenciesViaContainer(): void
    {
        $contents = $this->readSource('src/App/AppKernel.php');

        self::assertStringContainsString('HttpRequestTrafficMonitor::class', $contents);
        self::assertStringContainsString('HttpClientInterceptorRegistry::class', $contents);
        self::assertStringContainsString('new HttpRequestGovernanceInterceptorProvider(', $contents);
        self::assertStringContainsString('new HttpRequestTrafficInterceptorProvider(', $contents);
        self::assertStringContainsString('new HttpRequestLogInterceptorProvider(', $contents);
        self::assertStringContainsString('new HttpClient(', $contents);
    }

    public function testRequestLogInterceptorUsesInjectedLogService(): void
    {
        $interceptor = $this->readSource('src/Http/HttpRequestLogInterceptor.php');
        $provider = $this->readSource('src/Http/HttpRequestLogInterceptorProvider.php');

        self::assertStringContainsString('private readonly Log $log', $interceptor);
        self::assertStringNotContainsString('Log::', $interceptor);
        self::assertStringContainsString('private readonly Log $log', $provider);
        self::assertStringContainsString('new HttpRequestLogInterceptor($this->log)', $provider);
    }

    public function testTrafficMonitorAndInterceptorsDoNotUseSingletonAccess(): void
    {
        foreach ([
            'src/Http/HttpRequestTrafficMonitor.php',
            'src/Http/HttpRequestTrafficInterceptor.php',
            'src/Http/HttpRequestTrafficInterceptorProvider.php',
            'src/Http/HttpRequestGovernanceInterceptor.php',
            'src/Scheduler/Scheduler.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringNotContainsString('getInstance()', $contents, $path);
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
