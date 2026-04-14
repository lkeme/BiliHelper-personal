<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityInfoUpdate;

use Bhp\Console\Cli\RuntimeException as CliRuntimeException;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\Plugin\Builtin\ActivityInfoUpdate\Internal\ActivityInfoUpdateRunner;

final class ActivityInfoUpdatePlugin extends BasePlugin
{

    /**
     * 初始化 ActivityInfoUpdatePlugin
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, false);
    }

    /**
     * @param array<string, mixed> $options
     * @param string[] $argv
     */
    public function execute(array $options = [], array $argv = []): void
    {
        $resolved = $this->resolveOptionsFromArgv($argv);
        $result = $this->runner()->update(
            $resolved['file'],
            $resolved['urls'],
        );

        $this->info("活动索引: 输出文件 {$result['path']}");
    }

    /**
     * @param string[] $argv
     * @return array{file:?string,urls:?string}
     */
    private function resolveOptionsFromArgv(array $argv): array
    {
        $file = null;
        $urls = null;
        $count = count($argv);
        for ($index = 0; $index < $count; $index++) {
            $token = (string)$argv[$index];
            if (($token === '--file' || $token === '-f') && isset($argv[$index + 1])) {
                $file = (string)$argv[$index + 1];
                $index++;
                continue;
            }

            if (($token === '--urls' || $token === '-u') && isset($argv[$index + 1])) {
                $urls = (string)$argv[$index + 1];
                $index++;
            }
        }

        if (($file === null || trim($file) === '') && ($urls === null || trim($urls) === '')) {
            throw new CliRuntimeException('ActivityInfoUpdate 需要 --file 或 --urls');
        }

        return [
            'file' => $file !== null && trim($file) !== '' ? $file : null,
            'urls' => $urls !== null && trim($urls) !== '' ? $urls : null,
        ];
    }

    /**
     * 处理runner
     * @return ActivityInfoUpdateRunner
     */
    private function runner(): ActivityInfoUpdateRunner
    {
        return new ActivityInfoUpdateRunner($this->appContext());
    }
}
