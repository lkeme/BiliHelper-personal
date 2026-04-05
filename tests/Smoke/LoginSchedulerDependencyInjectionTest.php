<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class LoginSchedulerDependencyInjectionTest extends TestCase
{
    public function testLoginGateStateServiceRequiresExplicitContextAndPendingFlowStore(): void
    {
        $contents = $this->readSource('src/Login/LoginGateStateService.php');

        self::assertStringContainsString('private readonly AppContext $context', $contents);
        self::assertStringContainsString('private readonly LoginPendingFlowStore $pendingFlowStore', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
        self::assertStringNotContainsString('?? new LoginPendingFlowStore()', $contents);
    }

    public function testLoginManualInterventionPolicyRequiresExplicitContextAndPendingFlowStore(): void
    {
        $contents = $this->readSource('src/Login/LoginManualInterventionPolicy.php');

        self::assertStringContainsString('private readonly AppContext $context', $contents);
        self::assertStringContainsString('private readonly LoginPendingFlowStore $pendingFlowStore', $contents);
        self::assertStringNotContainsString('Runtime::appContext()', $contents);
        self::assertStringNotContainsString('?? new LoginPendingFlowStore()', $contents);
    }

    public function testSchedulerDoesNotConstructLoginStateServicesInternally(): void
    {
        $contents = $this->readSource('src/Scheduler/Scheduler.php');

        self::assertStringNotContainsString('new LoginGateStateService()', $contents);
        self::assertStringNotContainsString('new LoginManualInterventionPolicy()', $contents);
    }

    public function testAppKernelConstructsSchedulerWithInjectedLoginStateServices(): void
    {
        $contents = $this->readSource('src/App/AppKernel.php');

        self::assertStringContainsString('LoginGateStateService::class', $contents);
        self::assertStringContainsString('LoginManualInterventionPolicy::class', $contents);
        self::assertStringContainsString('new Scheduler(', $contents);
    }

    public function testLoginPluginBuildsGateStateServiceWithoutRuntimeFallback(): void
    {
        $contents = $this->readSource('src/Login/Login.php');

        self::assertStringContainsString('new LoginGateStateService($this->appContext(), $this->pendingFlowStore())', $contents);
        self::assertStringContainsString('$this->appContext()->log()', $contents);
        self::assertStringNotContainsString('Log::', $contents);
        self::assertStringNotContainsString('new LoginGateStateService(Runtime::appContext())', $contents);
        self::assertStringNotContainsString('new LoginCaptchaService(Runtime::appContext())', $contents);
        self::assertStringNotContainsString('new LoginCredentialService(Runtime::appContext())', $contents);
        self::assertStringNotContainsString('new LoginSmsService(Runtime::appContext())', $contents);
        self::assertStringNotContainsString('new LoginTokenLifecycleService(' . PHP_EOL . '            Runtime::appContext(),', $contents);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
