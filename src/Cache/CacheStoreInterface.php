<?php declare(strict_types=1);

namespace Bhp\Cache;

interface CacheStoreInterface
{
    public function databasePath(): string;

    public function get(string $scope, string $key): mixed;

    public function set(string $scope, string $key, mixed $value): void;

    /**
     * @param array<string, mixed> $entries
     */
    public function importScope(string $scope, array $entries): void;

    public function clear(): void;
}
