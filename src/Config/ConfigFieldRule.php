<?php declare(strict_types=1);

namespace Bhp\Config;

final class ConfigFieldRule
{
    /**
     * @param list<int|string> $allowedValues
     */
    public function __construct(
        public readonly string $key,
        public readonly array $allowedValues = [],
        public readonly bool $validateUrl = false,
        public readonly ?string $requiredWhenEnabledBy = null,
        public readonly ?string $emptyMessage = null,
        public readonly ?string $invalidMessage = null,
    ) {
    }

    public function hasEnum(): bool
    {
        return $this->allowedValues !== [];
    }
}
