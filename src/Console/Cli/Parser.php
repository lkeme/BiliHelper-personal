<?php declare(strict_types=1);

namespace Bhp\Console\Cli;

class Parser
{
    /**
     * @var array<string, mixed>
     */
    protected array $_values = [];

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->_values;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setValues(array $values): void
    {
        $this->_values = $values;
    }
}
