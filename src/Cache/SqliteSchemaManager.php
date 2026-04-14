<?php declare(strict_types=1);

namespace Bhp\Cache;

final class SqliteSchemaManager
{
    private const META_TABLE = 'schema_versions';
    private const CACHE_SCHEMA_VERSION = 1;
    private const ACTIVITY_FLOW_SCHEMA_VERSION = 1;

    /**
     * 处理ensure缓存结构
     * @param \SQLite3 $connection
     * @return void
     */
    public function ensureCacheSchema(\SQLite3 $connection): void
    {
        $this->ensureMetaTable($connection);
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS cache_entries (
                scope TEXT NOT NULL,
                cache_key TEXT NOT NULL,
                value TEXT NOT NULL,
                updated_at INTEGER NOT NULL,
                PRIMARY KEY (scope, cache_key)
            )'
        );
        $this->recordSchemaVersion($connection, 'cache_entries', self::CACHE_SCHEMA_VERSION);
    }

    /**
     * 处理ensureActivity流程结构
     * @param \SQLite3 $connection
     * @param string $tableName
     * @return void
     */
    public function ensureActivityFlowSchema(\SQLite3 $connection, string $tableName = 'activity_flow_entries'): void
    {
        $this->ensureMetaTable($connection);
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS ' . $tableName . ' (
                scope TEXT NOT NULL,
                biz_date TEXT NOT NULL,
                flow_id TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                updated_at INTEGER NOT NULL,
                PRIMARY KEY (scope, biz_date, flow_id)
            )'
        );
        $this->recordSchemaVersion($connection, $tableName, self::ACTIVITY_FLOW_SCHEMA_VERSION);
    }

    /**
     * 处理ensureMeta表格
     * @param \SQLite3 $connection
     * @return void
     */
    private function ensureMetaTable(\SQLite3 $connection): void
    {
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::META_TABLE . ' (
                schema_name TEXT NOT NULL PRIMARY KEY,
                schema_version INTEGER NOT NULL,
                updated_at INTEGER NOT NULL
            )'
        );
    }

    /**
     * 记录结构Version
     * @param \SQLite3 $connection
     * @param string $schemaName
     * @param int $version
     * @return void
     */
    private function recordSchemaVersion(\SQLite3 $connection, string $schemaName, int $version): void
    {
        $statement = $connection->prepare(
            'INSERT INTO ' . self::META_TABLE . ' (schema_name, schema_version, updated_at)
             VALUES (:schema_name, :schema_version, :updated_at)
             ON CONFLICT(schema_name) DO UPDATE SET
                schema_version = excluded.schema_version,
                updated_at = excluded.updated_at'
        );
        if (!$statement instanceof \SQLite3Stmt) {
            throw new \RuntimeException('无法准备 schema_versions 写入语句');
        }

        $statement->bindValue(':schema_name', $schemaName, SQLITE3_TEXT);
        $statement->bindValue(':schema_version', $version, SQLITE3_INTEGER);
        $statement->bindValue(':updated_at', time(), SQLITE3_INTEGER);
        $result = $statement->execute();
        if ($result instanceof \SQLite3Result) {
            $result->finalize();
        }
    }
}
