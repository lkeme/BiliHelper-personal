<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow;

use Bhp\Cache\SqliteSchemaManager;
use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

final class ActivityFlowStore
{
    private const TABLE_NAME = 'activity_flow_entries';

    private ?SQLite3 $connection = null;
    private readonly SqliteSchemaManager $schemaManager;

    public function __construct(
        private readonly string $databasePath,
        private readonly string $scope = 'ActivityLottery',
    ) {
        if (trim($this->databasePath) === '') {
            throw new RuntimeException('ActivityFlowStore 数据库路径不能为空');
        }
        if (trim($this->scope) === '') {
            throw new RuntimeException('ActivityFlowStore scope 不能为空');
        }
        $this->schemaManager = new SqliteSchemaManager();
    }

    /**
     * @param ActivityFlow[] $flows
     */
    public function save(array $flows): void
    {
        $grouped = [];
        $inputSeenByDate = [];
        foreach ($flows as $index => $flow) {
            if (!$flow instanceof ActivityFlow) {
                throw new RuntimeException('ActivityFlowStore::save 参数非法，index=' . $index);
            }
            if (trim($flow->id()) === '') {
                throw new RuntimeException('ActivityFlowStore::save flow_id 不能为空，index=' . $index);
            }
            $bizDate = ActivityFlow::normalizeBizDate($flow->bizDate());
            $this->assertUniqueFlowIdInBucket(
                $inputSeenByDate,
                $bizDate,
                $flow->id(),
                'save',
                (int)$index,
                'input',
            );
            $grouped[$bizDate][] = $flow;
        }

        if ($grouped === []) {
            return;
        }

        $connection = $this->connection();
        $statement = $connection->prepare(
            'INSERT INTO ' . self::TABLE_NAME . ' (scope, biz_date, flow_id, payload_json, updated_at)
             VALUES (:scope, :biz_date, :flow_id, :payload_json, :updated_at)
             ON CONFLICT(scope, biz_date, flow_id)
             DO UPDATE SET payload_json = excluded.payload_json, updated_at = excluded.updated_at'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 ActivityFlowStore SQLite 写入语句');
        }

        $connection->exec('BEGIN IMMEDIATE TRANSACTION');
        try {
            foreach ($grouped as $bizDate => $items) {
                foreach ($items as $flow) {
                    $payload = $flow->toArray();
                    $payload['biz_date'] = $bizDate;
                    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    $statement->reset();
                    $statement->clear();
                    $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
                    $statement->bindValue(':biz_date', (string)$bizDate, SQLITE3_TEXT);
                    $statement->bindValue(':flow_id', $flow->id(), SQLITE3_TEXT);
                    $statement->bindValue(':payload_json', $payloadJson, SQLITE3_TEXT);
                    $statement->bindValue(':updated_at', $flow->updatedAt(), SQLITE3_INTEGER);
                    $result = $statement->execute();
                    if ($result instanceof SQLite3Result) {
                        $result->finalize();
                    }
                }
            }
            $connection->exec('COMMIT');
        } catch (\Throwable $throwable) {
            $connection->exec('ROLLBACK');
            throw $throwable;
        }
    }

    /**
     * @return ActivityFlow[]
     */
    public function load(string $bizDate): array
    {
        $normalizedBizDate = ActivityFlow::normalizeBizDate($bizDate);
        $statement = $this->connection()->prepare(
            'SELECT flow_id, payload_json
             FROM ' . self::TABLE_NAME . '
             WHERE scope = :scope AND biz_date = :biz_date
             ORDER BY flow_id ASC'
        );
        if (!$statement instanceof SQLite3Stmt) {
            throw new RuntimeException('无法准备 ActivityFlowStore SQLite 读取语句');
        }

        $statement->bindValue(':scope', $this->scope, SQLITE3_TEXT);
        $statement->bindValue(':biz_date', $normalizedBizDate, SQLITE3_TEXT);
        $result = $statement->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException(sprintf(
                'ActivityFlowStore::load 读取失败 biz_date=%s',
                $normalizedBizDate,
            ));
        }

        $flows = [];
        $seenFlowIds = [];
        $index = 0;
        try {
            while (true) {
                $row = $result->fetchArray(SQLITE3_ASSOC);
                if (!is_array($row)) {
                    break;
                }

                $flow = $this->restoreFlowFromStoredRow($row, $normalizedBizDate, $index, 'load');
                $this->assertFlowBizDateMatchesBucket($flow, $normalizedBizDate, 'load', $index);
                $this->assertUniqueFlowIdInBucket(
                    $seenFlowIds,
                    $normalizedBizDate,
                    $flow->id(),
                    'load',
                    $index,
                    'stored',
                );
                $flows[] = $flow;
                $index++;
            }
        } finally {
            $result->finalize();
        }

        return $flows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function restoreFlowFromStoredRow(array $row, string $bizDate, int $index, string $operation): ActivityFlow
    {
        $flowId = trim((string)($row['flow_id'] ?? ''));
        $payloadJson = $row['payload_json'] ?? null;
        if (!is_string($payloadJson)) {
            throw new RuntimeException(sprintf(
                'ActivityFlowStore::%s 解析失败 biz_date=%s index=%d flow_id=%s：payload_json 非法',
                $operation,
                $bizDate,
                $index,
                $flowId !== '' ? $flowId : '<empty>',
            ));
        }

        try {
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('payload 顶层容器非法');
            }

            return ActivityFlow::fromArray($payload);
        } catch (\Throwable $throwable) {
            throw new RuntimeException(sprintf(
                'ActivityFlowStore::%s 解析失败 biz_date=%s index=%d flow_id=%s: %s',
                $operation,
                $bizDate,
                $index,
                $flowId !== '' ? $flowId : '<empty>',
                $throwable->getMessage(),
            ), 0, $throwable);
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
                throw new RuntimeException('无法创建 ActivityFlowStore 数据库目录: ' . $directory);
            }
        }

        $connection = new SQLite3($this->databasePath);
        $connection->busyTimeout(5000);
        $connection->enableExceptions(true);
        $connection->exec('PRAGMA journal_mode = WAL');
        $connection->exec('PRAGMA synchronous = NORMAL');
        $this->schemaManager->ensureActivityFlowSchema($connection, self::TABLE_NAME);

        return $this->connection = $connection;
    }

    private function assertFlowBizDateMatchesBucket(
        ActivityFlow $flow,
        string $bucketBizDate,
        string $operation,
        int $index,
    ): void {
        $flowBizDate = $flow->bizDate();
        if ($flowBizDate === $bucketBizDate) {
            return;
        }

        throw new RuntimeException(sprintf(
            'ActivityFlowStore::%s biz_date 不一致 bucket=%s index=%d flow_id=%s flow.biz_date=%s',
            $operation,
            $bucketBizDate,
            $index,
            $flow->id(),
            $flowBizDate,
        ));
    }

    /**
     * @param array<string, mixed> $seenMap
     */
    private function assertUniqueFlowIdInBucket(
        array &$seenMap,
        string $bizDate,
        string $flowId,
        string $operation,
        int $index,
        string $source,
    ): void {
        $key = $bizDate . "\n" . $flowId;
        if (array_key_exists($key, $seenMap)) {
            throw new RuntimeException(sprintf(
                'ActivityFlowStore::%s 检测到重复 flow_id biz_date=%s source=%s index=%d flow_id=%s',
                $operation,
                $bizDate,
                $source,
                $index,
                $flowId,
            ));
        }

        $seenMap[$key] = true;
    }
}
