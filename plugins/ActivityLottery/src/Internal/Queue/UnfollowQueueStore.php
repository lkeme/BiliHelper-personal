<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Queue;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

final class UnfollowQueueStore
{
    private const TABLE_NAME = 'activity_unfollow_queue';
    private const STATUS_PENDING = 'pending';
    private const STATUS_RUNNING = 'running';
    private const STATUS_DONE = 'done';
    private const CLAIM_STALE_SECONDS = 900;

    private ?SQLite3 $connection = null;

    public function __construct(
        private readonly string $databasePath,
        private readonly string $scope = 'ActivityLottery',
    ) {
        if (trim($this->databasePath) === '') {
            throw new RuntimeException('UnfollowQueueStore 数据库路径不能为空');
        }
        if (trim($this->scope) === '') {
            throw new RuntimeException('UnfollowQueueStore scope 不能为空');
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function enqueue(string $accountKey, string $uid, string $sourceBizDate, array $meta = []): void
    {
        $normalizedAccountKey = trim($accountKey);
        $normalizedUid = trim($uid);
        if ($normalizedAccountKey === '' || $normalizedUid === '') {
            return;
        }

        $normalizedBizDate = ActivityFlow::normalizeBizDate($sourceBizDate);
        $now = time();
        $statement = $this->connection()->prepare(
            'INSERT INTO ' . self::TABLE_NAME . ' (
                scope,
                account_key,
                uid,
                source_biz_date,
                status,
                attempts,
                next_run_at,
                activity_id,
                task_id,
                last_error,
                created_at,
                updated_at
            ) VALUES (
                :scope,
                :account_key,
                :uid,
                :source_biz_date,
                :status,
                0,
                0,
                :activity_id,
                :task_id,
                :last_error,
                :created_at,
                :updated_at
            )
            ON CONFLICT(scope, account_key, uid, source_biz_date)
            DO UPDATE SET
                status = :status,
                attempts = 0,
                next_run_at = 0,
                activity_id = excluded.activity_id,
                task_id = excluded.task_id,
                last_error = :last_error,
                updated_at = :updated_at'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 UnfollowQueueStore 入队语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
        $statement->bindValue(':uid', $normalizedUid, SQLITE3_TEXT);
        $statement->bindValue(':source_biz_date', $normalizedBizDate, SQLITE3_TEXT);
        $statement->bindValue(':status', self::STATUS_PENDING, SQLITE3_TEXT);
        $statement->bindValue(':activity_id', trim((string)($meta['activity_id'] ?? '')), SQLITE3_TEXT);
        $statement->bindValue(':task_id', trim((string)($meta['task_id'] ?? '')), SQLITE3_TEXT);
        $statement->bindValue(':last_error', '', SQLITE3_TEXT);
        $statement->bindValue(':created_at', $now, SQLITE3_INTEGER);
        $statement->bindValue(':updated_at', $now, SQLITE3_INTEGER);
        $result = $statement->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimNext(string $accountKey, int $now): ?array
    {
        $normalizedAccountKey = trim($accountKey);
        if ($normalizedAccountKey === '') {
            return null;
        }

        $connection = $this->connection();
        $connection->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $statement = $connection->prepare(
                'SELECT uid, source_biz_date, attempts, next_run_at, activity_id, task_id
                 FROM ' . self::TABLE_NAME . '
                 WHERE scope = :scope
                   AND account_key = :account_key
                   AND status != :done_status
                   AND next_run_at <= :now
                   AND (
                        status = :pending_status
                        OR (status = :running_status AND updated_at <= :stale_before)
                   )
                 ORDER BY next_run_at ASC, created_at ASC, uid ASC
                 LIMIT 1'
            );
            if (!$statement instanceof SQLite3Stmt) {
                throw new RuntimeException('无法准备 UnfollowQueueStore claim 查询语句');
            }

            $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
            $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
            $statement->bindValue(':done_status', self::STATUS_DONE, SQLITE3_TEXT);
            $statement->bindValue(':pending_status', self::STATUS_PENDING, SQLITE3_TEXT);
            $statement->bindValue(':running_status', self::STATUS_RUNNING, SQLITE3_TEXT);
            $statement->bindValue(':now', $now, SQLITE3_INTEGER);
            $statement->bindValue(':stale_before', $now - self::CLAIM_STALE_SECONDS, SQLITE3_INTEGER);
            $result = $statement->execute();
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('UnfollowQueueStore claim 查询失败');
            }

            try {
                $row = $result->fetchArray(SQLITE3_ASSOC);
            } finally {
                $result->finalize();
            }

            if (!is_array($row)) {
                $connection->exec('COMMIT');
                return null;
            }

            $uid = trim((string)($row['uid'] ?? ''));
            $sourceBizDate = trim((string)($row['source_biz_date'] ?? ''));
            if ($uid === '' || $sourceBizDate === '') {
                throw new RuntimeException('UnfollowQueueStore claim 读取到非法队列项');
            }

            $update = $connection->prepare(
                'UPDATE ' . self::TABLE_NAME . '
                 SET status = :status,
                     attempts = attempts + 1,
                     updated_at = :updated_at
                 WHERE scope = :scope
                   AND account_key = :account_key
                   AND uid = :uid
                   AND source_biz_date = :source_biz_date'
            );
            if (!$update instanceof SQLite3Stmt) {
                throw new RuntimeException('无法准备 UnfollowQueueStore claim 更新语句');
            }

            $update->bindValue(':status', self::STATUS_RUNNING, SQLITE3_TEXT);
            $update->bindValue(':updated_at', $now, SQLITE3_INTEGER);
            $update->bindValue(':scope', $this->scope, SQLITE3_TEXT);
            $update->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
            $update->bindValue(':uid', $uid, SQLITE3_TEXT);
            $update->bindValue(':source_biz_date', $sourceBizDate, SQLITE3_TEXT);
            $updateResult = $update->execute();
            if ($updateResult instanceof SQLite3Result) {
                $updateResult->finalize();
            }

            $connection->exec('COMMIT');

            return [
                'account_key' => $normalizedAccountKey,
                'uid' => $uid,
                'source_biz_date' => $sourceBizDate,
                'attempts' => max(0, (int)($row['attempts'] ?? 0)) + 1,
                'next_run_at' => max(0, (int)($row['next_run_at'] ?? 0)),
                'activity_id' => trim((string)($row['activity_id'] ?? '')),
                'task_id' => trim((string)($row['task_id'] ?? '')),
            ];
        } catch (\Throwable $throwable) {
            $connection->exec('ROLLBACK');
            throw $throwable;
        }
    }

    public function markDone(string $accountKey, string $uid, string $sourceBizDate, int $now): void
    {
        $this->updateStatus(
            $accountKey,
            $uid,
            $sourceBizDate,
            self::STATUS_DONE,
            0,
            '',
            $now,
        );
    }

    public function markRetry(string $accountKey, string $uid, string $sourceBizDate, int $nextRunAt, string $error, int $now): void
    {
        $this->updateStatus(
            $accountKey,
            $uid,
            $sourceBizDate,
            self::STATUS_PENDING,
            max($now + 1, $nextRunAt),
            $error,
            $now,
        );
    }

    public function hasPending(string $accountKey): bool
    {
        $normalizedAccountKey = trim($accountKey);
        if ($normalizedAccountKey === '') {
            return false;
        }

        $statement = $this->connection()->prepare(
            'SELECT 1
             FROM ' . self::TABLE_NAME . '
             WHERE scope = :scope
               AND account_key = :account_key
               AND status != :done_status
             LIMIT 1'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 UnfollowQueueStore hasPending 查询语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
        $statement->bindValue(':done_status', self::STATUS_DONE, SQLITE3_TEXT);

        return $this->queryHasRow($statement);
    }

    public function nextPendingRunAt(string $accountKey): ?int
    {
        $normalizedAccountKey = trim($accountKey);
        if ($normalizedAccountKey === '') {
            return null;
        }

        $statement = $this->connection()->prepare(
            'SELECT MIN(next_run_at) AS next_run_at
             FROM ' . self::TABLE_NAME . '
             WHERE scope = :scope
               AND account_key = :account_key
               AND status != :done_status'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 UnfollowQueueStore nextPendingRunAt 查询语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
        $statement->bindValue(':done_status', self::STATUS_DONE, SQLITE3_TEXT);
        $result = $statement->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException('UnfollowQueueStore nextPendingRunAt 查询失败');
        }

        try {
            $row = $result->fetchArray(SQLITE3_ASSOC);
        } finally {
            $result->finalize();
        }

        if (!is_array($row) || !isset($row['next_run_at']) || $row['next_run_at'] === null) {
            return null;
        }

        return max(0, (int)$row['next_run_at']);
    }

    public function pruneFinished(int $beforeTimestamp): void
    {
        $statement = $this->connection()->prepare(
            'DELETE FROM ' . self::TABLE_NAME . '
             WHERE scope = :scope
               AND status = :done_status
               AND updated_at < :before_timestamp'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 UnfollowQueueStore 清理语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':done_status', self::STATUS_DONE, SQLITE3_TEXT);
        $statement->bindValue(':before_timestamp', $beforeTimestamp, SQLITE3_INTEGER);
        $result = $statement->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
    }

    private function connection(): SQLite3
    {
        if ($this->connection instanceof SQLite3) {
            return $this->connection;
        }

        $directory = dirname($this->databasePath);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('无法创建 UnfollowQueueStore 数据库目录: ' . $directory);
            }
        }

        $connection = new SQLite3($this->databasePath);
        $connection->busyTimeout(5000);
        $connection->enableExceptions(true);
        $connection->exec('PRAGMA journal_mode = WAL');
        $connection->exec('PRAGMA synchronous = NORMAL');
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_NAME . ' (
                scope TEXT NOT NULL,
                account_key TEXT NOT NULL,
                uid TEXT NOT NULL,
                source_biz_date TEXT NOT NULL,
                status TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                next_run_at INTEGER NOT NULL DEFAULT 0,
                activity_id TEXT NOT NULL DEFAULT \'\',
                task_id TEXT NOT NULL DEFAULT \'\',
                last_error TEXT NOT NULL DEFAULT \'\',
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                PRIMARY KEY (scope, account_key, uid, source_biz_date)
            )'
        );
        $connection->exec(
            'CREATE INDEX IF NOT EXISTS idx_' . self::TABLE_NAME . '_account_pending
             ON ' . self::TABLE_NAME . ' (scope, account_key, status, next_run_at, updated_at)'
        );

        return $this->connection = $connection;
    }

    private function queryHasRow(SQLite3Stmt $statement): bool
    {
        $result = $statement->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException('UnfollowQueueStore 查询失败');
        }

        try {
            $row = $result->fetchArray(SQLITE3_ASSOC);
        } finally {
            $result->finalize();
        }

        return is_array($row);
    }

    private function updateStatus(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $status,
        int $nextRunAt,
        string $error,
        int $now,
    ): void {
        $normalizedAccountKey = trim($accountKey);
        $normalizedUid = trim($uid);
        if ($normalizedAccountKey === '' || $normalizedUid === '') {
            return;
        }

        $normalizedBizDate = ActivityFlow::normalizeBizDate($sourceBizDate);
        $statement = $this->connection()->prepare(
            'UPDATE ' . self::TABLE_NAME . '
             SET status = :status,
                 next_run_at = :next_run_at,
                 last_error = :last_error,
                 updated_at = :updated_at
             WHERE scope = :scope
               AND account_key = :account_key
               AND uid = :uid
               AND source_biz_date = :source_biz_date'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 UnfollowQueueStore 状态更新语句');
        }

        $statement->bindValue(':status', $status, SQLITE3_TEXT);
        $statement->bindValue(':next_run_at', max(0, $nextRunAt), SQLITE3_INTEGER);
        $statement->bindValue(':last_error', trim($error), SQLITE3_TEXT);
        $statement->bindValue(':updated_at', $now, SQLITE3_INTEGER);
        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
        $statement->bindValue(':uid', $normalizedUid, SQLITE3_TEXT);
        $statement->bindValue(':source_biz_date', $normalizedBizDate, SQLITE3_TEXT);
        $result = $statement->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
    }
}
