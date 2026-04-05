<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class SchedulerLoggingDependencyInjectionTest extends TestCase
{
    public function testSchedulerUsesInjectedLogServiceInsteadOfStaticFacade(): void
    {
        $scheduler = $this->readSource('src/Scheduler/Scheduler.php');
        $kernel = $this->readSource('src/App/AppKernel.php');

        self::assertStringContainsString('private readonly Log $log', $scheduler);
        self::assertStringNotContainsString('Log::', $scheduler);
        self::assertStringContainsString('$services->get(Log::class)', $kernel);
        self::assertStringContainsString('new Scheduler(', $kernel);
    }

    private function readSource(string $relativePath): string
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $relativePath;
        $contents = file_get_contents($path);

        self::assertIsString($contents, 'Failed to read ' . $relativePath);

        return $contents;
    }
}
