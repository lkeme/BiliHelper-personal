<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Runtime;

final class ActivityLotteryClock
{
    /** @var \Closure(): int */
    private \Closure $nowResolver;

    public function __construct(?callable $nowResolver = null)
    {
        $this->nowResolver = $nowResolver !== null
            ? \Closure::fromCallable($nowResolver)
            : static fn (): int => time();
    }

    public function now(): int
    {
        return (int)($this->nowResolver)();
    }
}

