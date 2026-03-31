<?php declare(strict_types=1);

namespace Bhp\Profile;

use RuntimeException;

class ProfileProcessRunner
{
    public function __construct(
        private readonly string $appRoot,
        private readonly ?string $phpBinary = null,
    ) {
    }

    public function run(ProfileContext $profile, string $mode = 'mode:app'): ProfileRunResult
    {
        $command = [
            $this->phpBinary ?? PHP_BINARY,
            $this->appEntry(),
            $profile->name(),
            $mode,
        ];

        $startedAt = microtime(true);
        [$exitCode, $stdout, $stderr] = $this->runProcess($command, $this->workingDirectory());

        return new ProfileRunResult(
            $profile->name(),
            $command,
            $exitCode,
            microtime(true) - $startedAt,
            $stdout,
            $stderr,
        );
    }

    /**
     * @param ProfileContext[] $profiles
     * @return ProfileRunResult[]
     */
    public function runMany(array $profiles, string $mode = 'mode:app'): array
    {
        $results = [];
        foreach ($profiles as $profile) {
            $results[] = $this->run($profile, $mode);
        }

        return $results;
    }

    /**
     * @param string[] $command
     * @return array{0:int,1:string,2:string}
     */
    protected function runProcess(array $command, string $cwd): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $commandLine = implode(' ', array_map('escapeshellarg', $command));
        $process = proc_open($commandLine, $descriptors, $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('无法启动 profile 子进程');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }

    protected function appEntry(): string
    {
        return rtrim(str_replace('\\', '/', $this->appRoot), '/') . '/app.php';
    }

    protected function workingDirectory(): string
    {
        return rtrim(str_replace('\\', '/', $this->appRoot), '/');
    }
}
