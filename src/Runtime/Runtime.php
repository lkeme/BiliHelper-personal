<?php declare(strict_types=1);

namespace Bhp\Runtime;

use Bhp\App\ServiceContainer;

final class Runtime
{
    /**
     * 初始化 Runtime
     * @param ServiceContainer $container
     * @param AppContext $context
     */
    public function __construct(
        private readonly ServiceContainer $container,
        private readonly AppContext $context,
    ) {
    }

    /**
     * 处理container
     * @return ServiceContainer
     */
    public function container(): ServiceContainer
    {
        return $this->container;
    }

    /**
     * 处理应用上下文
     * @return AppContext
     */
    public function appContext(): AppContext
    {
        return $this->context;
    }
}
