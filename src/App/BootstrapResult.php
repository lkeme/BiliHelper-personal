<?php declare(strict_types=1);

namespace Bhp\App;

use Bhp\Console\Console;
use Bhp\Runtime\AppContext;

final class BootstrapResult
{
    /**
     * 初始化 BootstrapResult
     * @param ServiceContainer $container
     * @param AppContext $context
     * @param Console $console
     * @param string $mode
     */
    public function __construct(
        public readonly ServiceContainer $container,
        public readonly AppContext $context,
        public readonly Console $console,
        public readonly string $mode,
    ) {
    }
}
