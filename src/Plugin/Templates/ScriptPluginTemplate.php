<?php declare(strict_types=1);

use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;

final class ScriptPluginTemplate extends BasePlugin
{
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
        // todo ...
    }
}
