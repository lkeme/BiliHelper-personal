<?php declare(strict_types=1);

namespace Bhp\Runtime;

use Bhp\App\ServiceContainer;

final class Runtime
{
    public function __construct(
        private readonly ServiceContainer $container,
        private readonly AppContext $context,
    ) {
    }

    public function container(): ServiceContainer
    {
        return $this->container;
    }

    public function appContext(): AppContext
    {
        return $this->context;
    }
}
