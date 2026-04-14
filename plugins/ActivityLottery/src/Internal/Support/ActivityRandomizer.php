<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Support;

final class ActivityRandomizer
{
    /** @var \Closure(int, int): int */
    private \Closure $intGenerator;

    /**
     * 初始化 ActivityRandomizer
     * @param callable $intGenerator
     */
    public function __construct(?callable $intGenerator = null)
    {
        $this->intGenerator = $intGenerator !== null
            ? \Closure::fromCallable($intGenerator)
            : static fn (int $min, int $max): int => random_int($min, $max);
    }

    /**
     * @template T
     * @param list<T> $items
     * @return T|null
     */
    public function pickOne(array $items): mixed
    {
        if ($items === []) {
            return null;
        }

        $index = ($this->intGenerator)(0, count($items) - 1);
        return $items[$index] ?? null;
    }
}

