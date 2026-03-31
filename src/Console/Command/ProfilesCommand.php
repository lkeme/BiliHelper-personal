<?php declare(strict_types=1);

namespace Bhp\Console\Command;

use Bhp\Console\Cli\Command;
use Bhp\Console\Cli\Interactor;
use Bhp\Console\Cli\RuntimeException as CliRuntimeException;
use Bhp\Log\Log;
use Bhp\Profile\ProfileContext;
use Bhp\Profile\ProfileProcessRunner;
use Bhp\Profile\ProfileRegistry;
use Bhp\Runtime\Runtime;

class ProfilesCommand extends Command
{
    protected string $desc = '[Profiles模式] 顺序执行多个 profile';

    public function __construct()
    {
        parent::__construct('mode:profiles', $this->desc);
        $this
            ->option('-p --profiles', '指定 profile 列表，逗号分隔')
            ->option('-a --all', '执行全部 profile')
            ->usage(
                '<bold>  $0</end> <comment>user mode:profiles</end><eol/>' .
                '<bold>  $0</end> <comment>user m:p</end><eol/>' .
                '<bold>  $0</end> <comment>mode:profiles --all</end><eol/>' .
                '<bold>  $0</end> <comment>m:p -a</end><eol/>' .
                '<bold>  $0</end> <comment>mode:profiles --profiles alpha,beta</end><eol/>'
            );
    }

    public function interact(Interactor $io): void
    {
    }

    public function execute(): void
    {
        Log::withContext(['caller' => 'ProfilesCommand'], function (): void {
            Log::info("执行 {$this->desc}");
            $profiles = $this->resolveProfiles();
            if ($profiles === []) {
                throw new CliRuntimeException('没有可执行的 profile');
            }

            $results = $this->createRunner()->runMany($profiles);
            $failed = [];
            foreach ($results as $result) {
                $status = $result->isSuccessful() ? 'SUCCESS' : 'FAILED';
                Log::info("[PROFILE.RUN] {$result->profile} {$status} exit={$result->exitCode} duration={$result->durationSeconds}s");
                if (!$result->isSuccessful()) {
                    $failed[] = $result->profile;
                }
            }

            if ($failed !== []) {
                throw new CliRuntimeException('以下 profile 执行失败: ' . implode(', ', $failed));
            }
        });
    }

    protected function createRunner(): ProfileProcessRunner
    {
        return new ProfileProcessRunner(Runtime::getInstance()->appContext()->appRoot());
    }

    /**
     * @return ProfileContext[]
     */
    protected function resolveProfiles(): array
    {
        $all = (bool)($this->values()['all'] ?? false);
        $input = trim((string)($this->values()['profiles'] ?? ''));
        $registry = new ProfileRegistry(Runtime::getInstance()->appContext()->appRoot());

        if ($all) {
            return array_values($registry->discover());
        }

        if ($input === '') {
            return [$registry->resolve(Runtime::getInstance()->appContext()->profileName())];
        }

        $profiles = [];
        foreach (preg_split('/[|,]/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $name) {
            $profiles[] = $registry->resolve(trim($name));
        }

        return $profiles;
    }
}
