<?php declare(strict_types=1);

namespace Bhp\Plugin;

use DateTimeImmutable;
use DateTimeZone;

final class PluginManifest
{
    /**
     * @param string[] $requiredExtensions
     * @param string[] $providesCapabilities
     * @param string[] $requiresCapabilities
     * @param array<int, array{url: string, comment: string}> $referenceLinks
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $hook,
        public readonly string $name,
        public readonly string $version,
        public readonly string $desc,
        public readonly int $priority,
        public readonly string $cycle,
        public readonly string $validUntil,
        public readonly string $activityUrl = '',
        public readonly array $referenceLinks = [],
        public readonly string $phpMin = '8.5.0',
        public readonly ?string $phpMax = null,
        public readonly array $requiredExtensions = [],
        public readonly array $providesCapabilities = [],
        public readonly array $requiresCapabilities = [],
        public readonly array $extra = [],
        public readonly bool $activityUrlDeclared = true,
        public readonly bool $referenceLinksDeclared = true,
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
            'valid_until',
            'activity_url',
            'reference_links',
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
            (string)($manifest['valid_until'] ?? ''),
            is_string($manifest['activity_url'] ?? null) ? (string)$manifest['activity_url'] : '',
            self::normalizeReferenceLinks($manifest['reference_links'] ?? []),
            (string)($manifest['php_min'] ?? '8.5.0'),
            array_key_exists('php_max', $manifest) && $manifest['php_max'] === null ? null : (isset($manifest['php_max']) ? (string)$manifest['php_max'] : null),
            self::normalizeStringList($manifest['required_extensions'] ?? []),
            self::normalizeStringList($manifest['provides_capabilities'] ?? []),
            self::normalizeStringList($manifest['requires_capabilities'] ?? []),
            $extra,
            array_key_exists('activity_url', $manifest),
            array_key_exists('reference_links', $manifest),
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
            'valid_until' => $this->validUntil,
            'activity_url' => $this->activityUrl,
            'reference_links' => $this->referenceLinks,
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

    /**
     * @return array<int, array{url: string, comment: string}>
     */
    private static function normalizeReferenceLinks(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = [
                'url' => is_string($item['url'] ?? null) ? (string)$item['url'] : '',
                'comment' => is_string($item['comment'] ?? null) ? (string)$item['comment'] : '',
            ];
        }

        return array_values($normalized);
    }

    /**
     * 解析Manifest日期时间
     * @param string $value
     * @param DateTimeZone $timezone
     * @return ?DateTimeImmutable
     */
    public static function parseManifestDateTime(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $timezone ??= new DateTimeZone('Asia/Shanghai');
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $normalized, $timezone);
        if (!$dateTime instanceof DateTimeImmutable) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return null;
        }

        return $dateTime->format('Y-m-d H:i:s') === $normalized ? $dateTime : null;
    }
}
