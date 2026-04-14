<?php declare(strict_types=1);

namespace Bhp\Cache;

final class SqliteCacheStore implements CacheStoreInterface
{
    private ?\SQLite3 $connection = null;
    private readonly SqliteSchemaManager $schemaManager;

    /**
     * 初始化 SqliteCacheStore
     * @param string $databasePath
     */
    public function __construct(private readonly string $databasePath)
    {
        $this->schemaManager = new SqliteSchemaManager();
    }

    /**
     * 处理databasePath
     * @return string
     */
    public function databasePath(): string
    {
        return $this->databasePath;
    }

    /**
     * 处理get
     * @param string $scope
     * @param string $key
     * @return mixed
     */
    public function get(string $scope, string $key): mixed
    {
        $statement = $this->connection()->prepare(
            'SELECT value FROM cache_entries WHERE scope = :scope AND cache_key = :cache_key LIMIT 1'
        );
        if (!$statement instanceof \SQLite3Stmt) {
            return false;
        }

        $statement->bindValue(':scope', $scope, SQLITE3_TEXT);
        $statement->bindValue(':cache_key', $key, SQLITE3_TEXT);
        $result = $statement->execute();
        if (!$result instanceof \SQLite3Result) {
            return false;
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);
        $result->finalize();

        if (!is_array($row) || !isset($row['value']) || !is_string($row['value'])) {
            return false;
        }

        return json_decode($row['value'], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * 处理设置
     * @param string $scope
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $scope, string $key, mixed $value): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO cache_entries(scope, cache_key, value, updated_at)
             VALUES (:scope, :cache_key, :value, :updated_at)
             ON CONFLICT(scope, cache_key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
        );
        if (!$statement instanceof \SQLite3Stmt) {
            throw new \RuntimeException('无法准备 SQLite 写入语句');
        }

        $statement->bindValue(':scope', $scope, SQLITE3_TEXT);
        $statement->bindValue(':cache_key', $key, SQLITE3_TEXT);
        $statement->bindValue(':value', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), SQLITE3_TEXT);
        $statement->bindValue(':updated_at', time(), SQLITE3_INTEGER);
        $statement->execute();
    }

    /**
     * @param array<string, mixed> $entries
     */
    public function importScope(string $scope, array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $connection = $this->connection();
        $connection->exec('BEGIN IMMEDIATE TRANSACTION');

        try {
            foreach ($entries as $key => $value) {
                $this->set($scope, (string) $key, $value);
            }
            $connection->exec('COMMIT');
        } catch (\Throwable $throwable) {
            $connection->exec('ROLLBACK');
            throw $throwable;
        }
    }

    /**
     * 清空数据
     * @return void
     */
    public function clear(): void
    {
        $path = $this->databasePath;

        if ($this->connection instanceof \SQLite3) {
            $this->purgeEntries($this->connection);
            @$this->connection->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        } elseif (is_file($path)) {
            $connection = new \SQLite3($path);
            $connection->busyTimeout(5000);
            $connection->enableExceptions(true);
            $this->purgeEntries($connection);
            @$connection->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $connection->close();
        }

        if ($this->connection instanceof \SQLite3) {
            $this->connection->close();
            $this->connection = null;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * 处理connection
     * @return \SQLite3
     */
    private function connection(): \SQLite3
    {
        if ($this->connection instanceof \SQLite3) {
            return $this->connection;
        }

        $connection = new \SQLite3($this->databasePath);
        $connection->busyTimeout(5000);
        $connection->enableExceptions(true);
        $connection->exec('PRAGMA journal_mode = WAL');
        $connection->exec('PRAGMA synchronous = NORMAL');
        $this->schemaManager->ensureCacheSchema($connection);

        return $this->connection = $connection;
    }

    /**
     * 处理purgeEntries
     * @param \SQLite3 $connection
     * @return void
     */
    private function purgeEntries(\SQLite3 $connection): void
    {
        $this->schemaManager->ensureCacheSchema($connection);
        $connection->exec('BEGIN IMMEDIATE TRANSACTION');

        try {
            $connection->exec('DELETE FROM cache_entries');
            $connection->exec('COMMIT');
        } catch (\Throwable $throwable) {
            @$connection->exec('ROLLBACK');
            throw $throwable;
        }
    }
}
