<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class OperationalServiceLoggingDependencyInjectionTest extends TestCase
{
    public function testEnvUsesInjectedLogService(): void
    {
        $contents = $this->readSource('src/Env/Env.php');

        self::assertStringContainsString('private readonly Log $log', $contents);
        self::assertStringNotContainsString('Log::', $contents);
    }

    public function testConsoleCommandsUseInjectedLogServiceAndConsoleDoesNotFallbackConstructThem(): void
    {
        $console = $this->readSource('src/Console/Console.php');
        self::assertStringNotContainsString('new AppCommand()', $console);
        self::assertStringNotContainsString('new DebugCommand()', $console);
        self::assertStringNotContainsString('new ScriptCommand($this->argv)', $console);

        foreach ([
            'src/Console/Command/AppCommand.php',
            'src/Console/Command/DebugCommand.php',
            'src/Console/Command/ScriptCommand.php',
        ] as $path) {
            $contents = $this->readSource($path);
            self::assertStringContainsString('private readonly Log $log', $contents, $path);
            self::assertStringNotContainsString('Log::', $contents, $path);
        }
    }

    public function testProfileAndLoginServicesDoNotUseStaticLogFacade(): void
    {
        foreach ([
            'src/Profile/ProfileCacheResetService.php' => '$this->context->log()',
            'src/Login/LoginCredentialService.php' => '$this->context->log()',
            'src/Login/LoginPendingFlowLifecycleService.php' => 'private readonly Log $log',
            'src/Login/LoginTokenLifecycleService.php' => '$this->context->log()',
        ] as $path => $expectedSnippet) {
            $contents = $this->readSource($path);
            self::assertStringContainsString($expectedSnippet, $contents, $path);
            self::assertStringNotContainsString('Log::', $contents, $path);
        }
    }

    public function testUserProfileServiceUsesInjectedLogService(): void
    {
        $contents = $this->readSource('src/User/UserProfileService.php');
        $context = $this->readSource('src/Runtime/AppContext.php');

        self::assertStringContainsString('private readonly Log $log', $contents);
        self::assertStringContainsString('private readonly ApiUser $apiUser', $contents);
        self::assertStringNotContainsString('Log::', $contents);
        self::assertStringContainsString('new UserProfileService(', $context);
        self::assertStringContainsString('$this->log(),', $context);
        self::assertStringContainsString('new \Bhp\Api\Vip\ApiUser($this->request())', $context);
    }

    public function testAppTerminatorDoesNotUseStaticLogFacade(): void
    {
        $contents = $this->readSource('src/Util/AppTerminator.php');

        self::assertStringNotContainsString('Log::', $contents);
        self::assertStringContainsString('fwrite(STDERR', $contents);
    }

    public function testAppKernelInjectsLogIntoOperationalServices(): void
    {
        $contents = $this->readSource('src/App/AppKernel.php');

        self::assertStringContainsString('new AppCommand(', $contents);
        self::assertStringContainsString('new Env(', $contents);
        self::assertStringContainsString('new DebugCommand(', $contents);
        self::assertStringContainsString('new ScriptCommand(', $contents);
        self::assertGreaterThanOrEqual(4, substr_count($contents, '$services->get(Log::class)'));
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
