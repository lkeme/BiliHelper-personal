<?php declare(strict_types=1);

namespace Bhp\Plugin;

final class PluginManifest
{
    /**
     * @param string[] $requiredExtensions
     * @param string[] $providesCapabilities
     * @param string[] $requiresCapabilities
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $hook,
        public readonly string $name,
        public readonly string $version,
        public readonly string $desc,
        public readonly int $priority,
        public readonly string $cycle,
        public readonly string $phpMin = '8.5.0',
        public readonly ?string $phpMax = null,
        public readonly array $requiredExtensions = [],
        public readonly array $providesCapabilities = [],
        public readonly array $requiresCapabilities = [],
        public readonly array $extra = [],
        public readonly bool $declared = true,
    ) {
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public static function fromArray(array $manifest): self
    {
        $knownKeys = [
            'hook',
            'name',
            'version',
            'desc',
            'priority',
            'cycle',
            'php_min',
            'php_max',
            'required_extensions',
            'provides_capabilities',
            'requires_capabilities',
        ];

        $extra = array_diff_key($manifest, array_flip($knownKeys));

        return new self(
            (string)($manifest['hook'] ?? ''),
            (string)($manifest['name'] ?? ''),
            (string)($manifest['version'] ?? ''),
            (string)($manifest['desc'] ?? ''),
            (int)($manifest['priority'] ?? 0),
            (string)($manifest['cycle'] ?? ''),
            (string)($manifest['php_min'] ?? '8.5.0'),
            array_key_exists('php_max', $manifest) && $manifest['php_max'] === null ? null : (isset($manifest['php_max']) ? (string)$manifest['php_max'] : null),
            self::normalizeStringList($manifest['required_extensions'] ?? []),
            self::normalizeStringList($manifest['provides_capabilities'] ?? []),
            self::normalizeStringList($manifest['requires_capabilities'] ?? []),
            $extra,
            $manifest !== [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->extra, [
            'hook' => $this->hook,
            'name' => $this->name,
            'version' => $this->version,
            'desc' => $this->desc,
            'priority' => $this->priority,
            'cycle' => $this->cycle,
            'php_min' => $this->phpMin,
            'php_max' => $this->phpMax,
            'required_extensions' => $this->requiredExtensions,
            'provides_capabilities' => $this->providesCapabilities,
            'requires_capabilities' => $this->requiresCapabilities,
        ]);
    }

    /**
     * @return string[]
     */
    private static function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
