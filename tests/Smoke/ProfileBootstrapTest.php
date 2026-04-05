<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\App\AppKernel;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProfileBootstrapTest extends TestCase
{
    private ?string $profileName = null;
    private ?string $profileRoot = null;
    /**
     * @var string[]
     */
    private array $profileRoots = [];

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_reverse($this->profileRoots) as $profileRoot) {
            if (!is_dir($profileRoot)) {
                continue;
            }

            $this->deleteDirectory($profileRoot);
        }
    }

    public function testProfileHelpCreatesLogAndCacheDirectories(): void
    {
        $root = dirname(__DIR__, 2);
        $this->profileName = 'tmp-smoke-' . bin2hex(random_bytes(6));
        $this->profileRoot = $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $this->profileName;
        $this->profileRoots[] = $this->profileRoot;
        $configPath = $this->profileRoot . DIRECTORY_SEPARATOR . 'config';

        self::assertTrue(mkdir($configPath, 0777, true) || is_dir($configPath));

        $exampleConfig = $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'user.ini';
        self::assertTrue(copy($exampleConfig, $configPath . DIRECTORY_SEPARATOR . 'user.ini'));

        [$exitCode, $output] = $this->runAppProfileHelp($root, $this->profileName);

        self::assertSame(0, $exitCode, $output);
        self::assertDirectoryExists($this->profileRoot . DIRECTORY_SEPARATOR . 'log');
        self::assertDirectoryExists($this->profileRoot . DIRECTORY_SEPARATOR . 'cache');
    }

    public function testKernelBootReturnsExplicitBootstrapResultForHelpMode(): void
    {
        $root = dirname(__DIR__, 2);
        $this->profileName = 'tmp-kernel-' . bin2hex(random_bytes(6));
        $this->profileRoot = $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $this->profileName;
        $this->profileRoots[] = $this->profileRoot;
        $configPath = $this->profileRoot . DIRECTORY_SEPARATOR . 'config';

        self::assertTrue(mkdir($configPath, 0777, true) || is_dir($configPath));

        $exampleConfig = $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'user.ini';
        self::assertTrue(copy($exampleConfig, $configPath . DIRECTORY_SEPARATOR . 'user.ini'));

        $kernel = new AppKernel($root, ['app.php', $this->profileName, '--help']);
        $result = $kernel->boot();

        self::assertSame($this->profileName, $result->context->profileName());
        self::assertSame('app', $result->mode);
        self::assertDirectoryExists($this->profileRoot . DIRECTORY_SEPARATOR . 'log');
        self::assertDirectoryExists($this->profileRoot . DIRECTORY_SEPARATOR . 'cache');
    }

    public function testMultipleKernelBootsKeepProfileSpecificContextAndConfigIsolated(): void
    {
        $root = dirname(__DIR__, 2);
        $firstProfile = 'tmp-kernel-a-' . bin2hex(random_bytes(4));
        $secondProfile = 'tmp-kernel-b-' . bin2hex(random_bytes(4));

        $firstRoot = $this->createProfileFixture($root, $firstProfile, 'alpha-user');
        $secondRoot = $this->createProfileFixture($root, $secondProfile, 'beta-user');

        $firstResult = (new AppKernel($root, ['app.php', $firstProfile, '--help']))->boot();
        $secondResult = (new AppKernel($root, ['app.php', $secondProfile, '--help']))->boot();

        self::assertSame($firstProfile, $firstResult->context->profileName());
        self::assertSame($secondProfile, $secondResult->context->profileName());
        self::assertSame('alpha-user', (string)$firstResult->context->config('print.uname', '', 'string'));
        self::assertSame('beta-user', (string)$secondResult->context->config('print.uname', '', 'string'));
        self::assertSame(str_replace('\\', '/', $firstRoot . '/config/'), $firstResult->context->configPath());
        self::assertSame(str_replace('\\', '/', $secondRoot . '/config/'), $secondResult->context->configPath());
    }

    /**
     * @return array{int, string}
     */
    private function runAppProfileHelp(string $root, string $profileName): array
    {
        $command = [PHP_BINARY, 'app.php', $profileName, '--help'];
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

    private function deleteDirectory(string $directory): void
    {
        $rootRealPath = realpath($directory);
        if ($rootRealPath === false) {
            throw new RuntimeException(sprintf('Failed to resolve directory path for cleanup: %s', $directory));
        }

        $this->deleteDirectoryContents($directory, $rootRealPath);
        if (!rmdir($directory)) {
            throw new RuntimeException(sprintf('Failed to remove directory during cleanup: %s', $directory));
        }
    }

    private function deleteDirectoryContents(string $directory, string $rootRealPath): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException(sprintf('Failed to read directory during cleanup: %s', $directory));
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path)) {
                if (!unlink($path)) {
                    throw new RuntimeException(sprintf('Failed to unlink symbolic link during cleanup: %s', $path));
                }

                continue;
            }

            if (is_dir($path)) {
                $realPath = realpath($path);
                if ($realPath === false) {
                    throw new RuntimeException(sprintf('Failed to resolve child directory path during cleanup: %s', $path));
                }

                if (!$this->isPathWithinRoot($realPath, $rootRealPath)) {
                    throw new RuntimeException(sprintf('Refusing to delete path outside profile root: %s', $path));
                }

                $this->deleteDirectoryContents($path, $rootRealPath);
                if (!rmdir($path)) {
                    throw new RuntimeException(sprintf('Failed to remove directory during cleanup: %s', $path));
                }

                continue;
            }

            if (!is_file($path)) {
                throw new RuntimeException(sprintf('Encountered unsupported filesystem entry during cleanup: %s', $path));
            }

            if (!unlink($path)) {
                throw new RuntimeException(sprintf('Failed to remove file during cleanup: %s', $path));
            }
        }
    }

    private function isPathWithinRoot(string $path, string $rootRealPath): bool
    {
        if ($path === $rootRealPath) {
            return true;
        }

        return str_starts_with($path, rtrim($rootRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    private function createProfileFixture(string $root, string $profileName, string $uname): string
    {
        $profileRoot = $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $profileName;
        $this->profileRoots[] = $profileRoot;
        $configPath = $profileRoot . DIRECTORY_SEPARATOR . 'config';

        self::assertTrue(mkdir($configPath, 0777, true) || is_dir($configPath));

        $exampleConfig = $root . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . 'example' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'user.ini';
        $config = file_get_contents($exampleConfig);
        self::assertIsString($config);

        $lineEnding = str_contains($config, "\r\n") ? "\r\n" : "\n";
        $normalizedConfig = str_replace(["\r\n", "\r"], "\n", $config);
        $updatedConfig = preg_replace('/^uname\s*=.*$/m', 'uname = "' . $uname . '"', $normalizedConfig);
        self::assertIsString($updatedConfig);
        $config = $lineEnding === "\n" ? $updatedConfig : str_replace("\n", $lineEnding, $updatedConfig);

        self::assertIsString($config);
        self::assertNotSame('', $config);
        self::assertNotFalse(file_put_contents($configPath . DIRECTORY_SEPARATOR . 'user.ini', $config));

        return $profileRoot;
    }
}
