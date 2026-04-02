<?php declare(strict_types=1);

require_once __DIR__ . '/Internal/bootstrap.php';

use Bhp\Console\Cli\RuntimeException as CliRuntimeException;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\Plugin\ActivityInfoUpdate\Internal\ActivityInfoUpdateRunner;

final class ActivityInfoUpdate extends BasePlugin
{
    public ?array $info = [
        'hook' => __CLASS__,
        'name' => 'ActivityInfoUpdate',
        'version' => '0.0.1',
        'desc' => '更新活动索引',
        'author' => 'Lkeme',
        'priority' => 1900,
        'cycle' => 'manual',
        'mode' => 'script',
    ];

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
        $result = (new ActivityInfoUpdateRunner())->update(
            $resolved['file'],
            $resolved['urls'],
        );

        Log::info("活动索引: 输出文件 {$result['path']}");
    }

    /**
     * @param string[] $argv
     * @return array{file:?string,urls:?string}
     */
    protected function resolveOptionsFromArgv(array $argv): array
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
}
