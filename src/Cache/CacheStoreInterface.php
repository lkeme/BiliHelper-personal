<?php declare(strict_types=1);

namespace Bhp\Cache;

interface CacheStoreInterface
{
    /**
     * 处理databasePath
     * @return string
     */
    public function databasePath(): string;

    /**
     * 处理get
     * @param string $scope
     * @param string $key
     * @return mixed
     */
    public function get(string $scope, string $key): mixed;

    /**
     * 处理设置
     * @param string $scope
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $scope, string $key, mixed $value): void;

    /**
     * @param array<string, mixed> $entries
     */
    public function importScope(string $scope, array $entries): void;

    /**
     * 清空数据
     * @return void
     */
    public function clear(): void;
}
