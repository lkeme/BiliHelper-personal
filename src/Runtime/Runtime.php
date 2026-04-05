<?php declare(strict_types=1);

namespace Bhp\Runtime;

use Bhp\App\ServiceContainer;
use RuntimeException;

final class Runtime
{
    private static ?self $current = null;

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly AppContext $context,
    ) {
    }

    public static function activate(self $runtime): self
    {
        self::$current = $runtime;

        return $runtime;
    }

    public static function hasCurrent(): bool
    {
        return self::$current instanceof self;
    }

    public static function current(): self
    {
        if (!self::hasCurrent()) {
            throw new RuntimeException('Runtime has not been bootstrapped.');
        }

        return self::$current;
    }

    public static function service(string $id): mixed
    {
        return self::current()->container->get($id);
    }

    public static function context(): AppContext
    {
        return self::current()->context;
    }

    public static function appContext(): AppContext
    {
        return self::current()->context;
    }

    public function container(): ServiceContainer
    {
        return $this->container;
    }
}
