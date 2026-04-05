<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class ConsoleHelpTest extends TestCase
{
    public function testAppHelpListsExpectedModesAndDoesNotListDoctor(): void
    {
        [$exitCode, $output] = $this->runAppHelp();

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('mode:app', $output);
        self::assertStringContainsString('mode:debug', $output);
        self::assertStringContainsString('mode:script', $output);
        self::assertStringNotContainsString('mode:doctor', $output);
    }

    /**
     * @return array{int, string}
     */
    private function runAppHelp(): array
    {
        $root = dirname(__DIR__, 2);
        $command = [PHP_BINARY, 'app.php', '--help'];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $root);
        self::assertIsResource($process, 'Failed to start PHP process');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $status = proc_close($process);

        return [$status, $stdout . $stderr];
    }
}
