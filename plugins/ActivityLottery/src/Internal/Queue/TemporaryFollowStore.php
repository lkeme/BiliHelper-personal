<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Queue;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

final class TemporaryFollowStore
{
    public const STATE_PLANNED = 'planned';
    public const STATE_CLEANUP_PENDING = 'cleanup_pending';
    public const STATE_CLEANUP_ENQUEUED = 'cleanup_enqueued';
    public const STATE_DONE = 'done';
    public const STATE_CANCELLED = 'cancelled';

    private const TABLE_NAME = 'activity_temporary_follow_ops';

    private ?SQLite3 $connection = null;

    public function __construct(
        private readonly string $databasePath,
        private readonly string $scope = 'ActivityLottery',
    ) {
        if (trim($this->databasePath) === '') {
            throw new RuntimeException('TemporaryFollowStore 数据库路径不能为空');
        }
        if (trim($this->scope) === '') {
            throw new RuntimeException('TemporaryFollowStore scope 不能为空');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $activityId,
        string $taskId,
    ): ?array {
        $normalizedAccountKey = trim($accountKey);
        $normalizedUid = trim($uid);
        $normalizedActivityId = trim($activityId);
        $normalizedTaskId = trim($taskId);
        if ($normalizedAccountKey === '' || $normalizedUid === '' || $normalizedTaskId === '') {
            return null;
        }

        $statement = $this->connection()->prepare(
            'SELECT uid, source_biz_date, activity_id, task_id, precheck_following, state, last_error, created_at, updated_at
             FROM ' . self::TABLE_NAME . '
             WHERE scope = :scope
               AND account_key = :account_key
               AND uid = :uid
               AND source_biz_date = :source_biz_date
               AND activity_id = :activity_id
               AND task_id = :task_id
             LIMIT 1'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 TemporaryFollowStore 查询语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
        $statement->bindValue(':uid', $normalizedUid, SQLITE3_TEXT);
        $statement->bindValue(':source_biz_date', ActivityFlow::normalizeBizDate($sourceBizDate), SQLITE3_TEXT);
        $statement->bindValue(':activity_id', $normalizedActivityId, SQLITE3_TEXT);
        $statement->bindValue(':task_id', $normalizedTaskId, SQLITE3_TEXT);
        $result = $statement->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException('TemporaryFollowStore 查询失败');
        }

        try {
            $row = $result->fetchArray(SQLITE3_ASSOC);
        } finally {
            $result->finalize();
        }

        if (!is_array($row)) {
            return null;
        }

        $row['precheck_following'] = ((int)($row['precheck_following'] ?? 0)) === 1;
        return $row;
    }

    public function ensurePlanned(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        $this->upsert($accountKey, $uid, $sourceBizDate, $activityId, $taskId, false, self::STATE_PLANNED, '', $now);
    }

    public function markAlreadyFollowed(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        $this->upsert($accountKey, $uid, $sourceBizDate, $activityId, $taskId, true, self::STATE_CANCELLED, '', $now);
    }

    public function markCleanupPending(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        $this->upsert($accountKey, $uid, $sourceBizDate, $activityId, $taskId, false, self::STATE_CLEANUP_PENDING, '', $now);
    }

    public function markCleanupEnqueued(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        $this->upsert($accountKey, $uid, $sourceBizDate, $activityId, $taskId, false, self::STATE_CLEANUP_ENQUEUED, '', $now);
    }

    public function markDone(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $activityId,
        string $taskId,
        int $now,
    ): void {
        $this->upsert($accountKey, $uid, $sourceBizDate, $activityId, $taskId, false, self::STATE_DONE, '', $now);
    }

    public function markCancelled(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $activityId,
        string $taskId,
        int $now,
        string $error = '',
    ): void {
        $this->upsert($accountKey, $uid, $sourceBizDate, $activityId, $taskId, false, self::STATE_CANCELLED, $error, $now);
    }

    public function pruneFinished(int $beforeTimestamp): void
    {
        $statement = $this->connection()->prepare(
            'DELETE FROM ' . self::TABLE_NAME . '
             WHERE scope = :scope
               AND state IN (:done_state, :cancelled_state)
               AND updated_at < :before_timestamp'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 TemporaryFollowStore 清理语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':done_state', self::STATE_DONE, SQLITE3_TEXT);
        $statement->bindValue(':cancelled_state', self::STATE_CANCELLED, SQLITE3_TEXT);
        $statement->bindValue(':before_timestamp', $beforeTimestamp, SQLITE3_INTEGER);
        $result = $statement->execute();
        if ($result instanceof SQLite3Result) {
            $result->finalize();
        }
    }

    public function hasRecoverable(string $accountKey): bool
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
               AND state IN (:planned_state, :cleanup_pending_state)
             LIMIT 1'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 TemporaryFollowStore hasRecoverable 查询语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
        $statement->bindValue(':planned_state', self::STATE_PLANNED, SQLITE3_TEXT);
        $statement->bindValue(':cleanup_pending_state', self::STATE_CLEANUP_PENDING, SQLITE3_TEXT);

        return $this->queryHasRow($statement);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimNextRecoverable(string $accountKey, int $now): ?array
    {
        $normalizedAccountKey = trim($accountKey);
        if ($normalizedAccountKey === '') {
            return null;
        }

        $connection = $this->connection();
        $connection->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            $statement = $connection->prepare(
                'SELECT uid, source_biz_date, activity_id, task_id, precheck_following, state, last_error, created_at, updated_at
                 FROM ' . self::TABLE_NAME . '
                 WHERE scope = :scope
                   AND account_key = :account_key
                   AND state IN (:planned_state, :cleanup_pending_state)
                 ORDER BY updated_at ASC, created_at ASC, uid ASC
                 LIMIT 1'
            );
            if (!$statement instanceof SQLite3Stmt) {
                throw new RuntimeException('无法准备 TemporaryFollowStore recoverable 查询语句');
            }

            $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
            $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
            $statement->bindValue(':planned_state', self::STATE_PLANNED, SQLITE3_TEXT);
            $statement->bindValue(':cleanup_pending_state', self::STATE_CLEANUP_PENDING, SQLITE3_TEXT);
            $result = $statement->execute();
            if (!$result instanceof SQLite3Result) {
                throw new RuntimeException('TemporaryFollowStore recoverable 查询失败');
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
            $activityId = trim((string)($row['activity_id'] ?? ''));
            $taskId = trim((string)($row['task_id'] ?? ''));
            if ($uid === '' || $sourceBizDate === '' || $taskId === '') {
                throw new RuntimeException('TemporaryFollowStore recoverable 读取到非法记录');
            }

            $update = $connection->prepare(
                'UPDATE ' . self::TABLE_NAME . '
                 SET updated_at = :updated_at
                 WHERE scope = :scope
                   AND account_key = :account_key
                   AND uid = :uid
                   AND source_biz_date = :source_biz_date
                   AND activity_id = :activity_id
                   AND task_id = :task_id'
            );
            if (!$update instanceof SQLite3Stmt) {
                throw new RuntimeException('无法准备 TemporaryFollowStore recoverable 更新语句');
            }

            $update->bindValue(':updated_at', $now, SQLITE3_INTEGER);
            $update->bindValue(':scope', $this->scope, SQLITE3_TEXT);
            $update->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
            $update->bindValue(':uid', $uid, SQLITE3_TEXT);
            $update->bindValue(':source_biz_date', ActivityFlow::normalizeBizDate($sourceBizDate), SQLITE3_TEXT);
            $update->bindValue(':activity_id', $activityId, SQLITE3_TEXT);
            $update->bindValue(':task_id', $taskId, SQLITE3_TEXT);
            $updateResult = $update->execute();
            if ($updateResult instanceof SQLite3Result) {
                $updateResult->finalize();
            }

            $connection->exec('COMMIT');

            $row['uid'] = $uid;
            $row['source_biz_date'] = ActivityFlow::normalizeBizDate($sourceBizDate);
            $row['activity_id'] = $activityId;
            $row['task_id'] = $taskId;
            $row['precheck_following'] = ((int)($row['precheck_following'] ?? 0)) === 1;
            return $row;
        } catch (\Throwable $throwable) {
            $connection->exec('ROLLBACK');
            throw $throwable;
        }
    }

    private function upsert(
        string $accountKey,
        string $uid,
        string $sourceBizDate,
        string $activityId,
        string $taskId,
        bool $precheckFollowing,
        string $state,
        string $lastError,
        int $now,
    ): void {
        $normalizedAccountKey = trim($accountKey);
        $normalizedUid = trim($uid);
        $normalizedActivityId = trim($activityId);
        $normalizedTaskId = trim($taskId);
        if ($normalizedAccountKey === '' || $normalizedUid === '' || $normalizedTaskId === '') {
            return;
        }

        $statement = $this->connection()->prepare(
            'INSERT INTO ' . self::TABLE_NAME . ' (
                scope,
                account_key,
                uid,
                source_biz_date,
                activity_id,
                task_id,
                precheck_following,
                state,
                last_error,
                created_at,
                updated_at
            ) VALUES (
                :scope,
                :account_key,
                :uid,
                :source_biz_date,
                :activity_id,
                :task_id,
                :precheck_following,
                :state,
                :last_error,
                :created_at,
                :updated_at
            )
            ON CONFLICT(scope, account_key, uid, source_biz_date, activity_id, task_id)
            DO UPDATE SET
                precheck_following = excluded.precheck_following,
                state = excluded.state,
                last_error = excluded.last_error,
                updated_at = excluded.updated_at'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 TemporaryFollowStore 写入语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':account_key', $normalizedAccountKey, SQLITE3_TEXT);
        $statement->bindValue(':uid', $normalizedUid, SQLITE3_TEXT);
        $statement->bindValue(':source_biz_date', ActivityFlow::normalizeBizDate($sourceBizDate), SQLITE3_TEXT);
        $statement->bindValue(':activity_id', $normalizedActivityId, SQLITE3_TEXT);
        $statement->bindValue(':task_id', $normalizedTaskId, SQLITE3_TEXT);
        $statement->bindValue(':precheck_following', $precheckFollowing ? 1 : 0, SQLITE3_INTEGER);
        $statement->bindValue(':state', $state, SQLITE3_TEXT);
        $statement->bindValue(':last_error', trim($lastError), SQLITE3_TEXT);
        $statement->bindValue(':created_at', $now, SQLITE3_INTEGER);
        $statement->bindValue(':updated_at', $now, SQLITE3_INTEGER);
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
                throw new RuntimeException('无法创建 TemporaryFollowStore 数据库目录: ' . $directory);
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
                activity_id TEXT NOT NULL DEFAULT \'\',
                task_id TEXT NOT NULL DEFAULT \'\',
                precheck_following INTEGER NOT NULL DEFAULT 0,
                state TEXT NOT NULL,
                last_error TEXT NOT NULL DEFAULT \'\',
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                PRIMARY KEY (scope, account_key, uid, source_biz_date, activity_id, task_id)
            )'
        );
        $connection->exec(
            'CREATE INDEX IF NOT EXISTS idx_' . self::TABLE_NAME . '_account_state
             ON ' . self::TABLE_NAME . ' (scope, account_key, state, updated_at)'
        );

        return $this->connection = $connection;
    }

    private function queryHasRow(SQLite3Stmt $statement): bool
    {
        $result = $statement->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException('TemporaryFollowStore 查询失败');
        }

        try {
            $row = $result->fetchArray(SQLITE3_ASSOC);
        } finally {
            $result->finalize();
        }

        return is_array($row);
    }
}
