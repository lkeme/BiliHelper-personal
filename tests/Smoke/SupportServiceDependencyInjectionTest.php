<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class SupportServiceDependencyInjectionTest extends TestCase
{
    public function testGhProxySourceUsesInjectedAppContext(): void
    {
        $contents = $this->readSource('src/Util/GhProxy/GhProxy.php');

        self::assertStringContainsString('AppContext $context', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
    }

    public function testRemoteResourceResolverSourceUsesInjectedContextAndProxy(): void
    {
        $contents = $this->readSource('src/Remote/RemoteResourceResolver.php');

        self::assertStringContainsString('AppContext $context', $contents);
        self::assertStringContainsString('GhProxy $ghProxy', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
        self::assertStringNotContainsString('GhProxy::mirror(', $contents);
    }

    public function testLiveWatchServiceSourceDoesNotUseRuntimeAppContext(): void
    {
        $contents = $this->readSource('src/Automation/Watch/LiveWatchService.php');

        self::assertStringNotContainsString('Runtime::appContext()', $contents);
        self::assertStringContainsString('callable $userAgentResolver', $contents);
    }

    public function testActivityLotteryPluginInjectsContextIntoRemoteResolverAndLiveWatchService(): void
    {
        $contents = $this->readSource('plugins/ActivityLottery/src/ActivityLotteryPlugin.php');

        self::assertStringContainsString('new RemoteResourceResolver($this->appContext())', $contents);
        self::assertStringContainsString('new LiveWatchService(', $contents);
        self::assertStringContainsString("platform.headers.pc_ua", $contents);
    }

    public function testCheckUpdatePluginInjectsContextIntoRemoteResolver(): void
    {
        $contents = $this->readSource('plugins/CheckUpdate/src/CheckUpdatePlugin.php');

        self::assertStringContainsString('new RemoteResourceResolver($this->appContext())', $contents);
    }

    public function testActivityInfoUpdateRunnerUsesInjectedAppContextForRequestAndLogging(): void
    {
        $contents = $this->readSource('plugins/ActivityInfoUpdate/src/Internal/ActivityInfoUpdateRunner.php');

        self::assertStringContainsString('AppContext $appContext', $contents);
        self::assertStringContainsString('$this->appContext->request()', $contents);
        self::assertStringContainsString('$this->appContext->log()', $contents);
        self::assertStringNotContainsString('Request::get(', $contents);
        self::assertStringNotContainsString('Request::csrf(', $contents);
        self::assertStringNotContainsString('Request::uid(', $contents);
        self::assertStringNotContainsString('Log::', $contents);
    }

    public function testMainSiteArchiveServiceUsesInjectedLogger(): void
    {
        $contents = $this->readSource('plugins/MainSite/src/MainSiteArchiveService.php');

        self::assertStringContainsString('Log $log', $contents);
        self::assertStringNotContainsString('Log::', $contents);
    }

    public function testActivityLotterySupportServicesUseInjectedFetchersAndLogger(): void
    {
        foreach ([
            'plugins/ActivityLottery/src/Internal/Catalog/RemoteCatalogSource.php',
            'plugins/ActivityLottery/src/Internal/Gateway/ActivityLotteryGateway.php',
            'plugins/ActivityLottery/src/Internal/Gateway/WatchLiveGateway.php',
            'plugins/ActivityLottery/src/Internal/Runtime/ActivityLotteryRuntime.php',
        ] as $path) {
            $contents = $this->readSource($path);

            self::assertStringNotContainsString('Request::get(', $contents, $path);
            self::assertStringNotContainsString('Log::', $contents, $path);
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
