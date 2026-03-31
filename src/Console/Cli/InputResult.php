<?php declare(strict_types=1);

namespace Bhp\Console\Cli;

final class InputResult
{
    /**
     * @param string[] $args
     */
    public function __construct(private readonly array $args)
    {
    }

    /**
     * @return string[]
     */
    public function args(): array
    {
        return $this->args;
    }
}
