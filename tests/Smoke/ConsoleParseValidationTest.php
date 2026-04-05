<?php declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class ConsoleParseValidationTest extends TestCase
{
    public function testParseRejectsUnknownCommandTokenEvenAfterValidModeToken(): void
    {
        [$exitCode, $output] = $this->runInlinePhp(<<<'PHP'
require 'vendor/autoload.php';

try {
    \Bhp\Console\Console::parse(['app.php', 'm:a', 'mode:oops']);
    echo 'NO_EXCEPTION';
} catch (\Bhp\Console\Cli\RuntimeException $exception) {
    echo $exception->getMessage();
}
PHP);

        self::assertSame(0, $exitCode, $output);
        self::assertSame('未找到可执行命令: mode:oops', trim($output));
    }

    public function testParseRejectsReservedProfileWithCliRuntimeException(): void
    {
        [$exitCode, $output] = $this->runInlinePhp(<<<'PHP'
require 'vendor/autoload.php';

try {
    \Bhp\Console\Console::parse(['app.php', 'example', '--help']);
    echo 'NO_EXCEPTION';
} catch (\Bhp\Console\Cli\RuntimeException $exception) {
    echo $exception->getMessage();
}
PHP);

        self::assertSame(0, $exitCode, $output);
        self::assertSame('不能使用程序保留关键字 example', trim($output));
    }

    public function testAppEntrypointReportsInvalidCommandTokenAndExitsNonZero(): void
    {
        [$exitCode, $output] = $this->runCommand([PHP_BINARY, 'app.php', 'm:a', 'mode:oops', '--help']);

        self::assertSame(1, $exitCode, $output);
        self::assertStringContainsString('未找到可执行命令: mode:oops', $output);
    }

    /**
     * @return array{int, string}
     */
    private function runInlinePhp(string $script): array
    {
        return $this->runCommand([PHP_BINARY, '-r', $script]);
    }

    /**
     * @param list<string> $command
     * @return array{int, string}
     */
    private function runCommand(array $command): array
    {
        $root = dirname(__DIR__, 2);
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
