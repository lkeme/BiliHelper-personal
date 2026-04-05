<?php declare(strict_types=1);

namespace Tests\Smoke;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowContext;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlowStore;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SQLite3;

final class ActivityFlowStorePersistenceTest extends TestCase
{
    private const TABLE_NAME = 'activity_flow_entries';

    /**
     * @var string[]
     */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_reverse($this->tempDirectories) as $directory) {
            if (is_dir($directory)) {
                $this->deleteDirectory($directory);
            }
        }
    }

    public function testStoreUsesExplicitDatabasePathWithoutProfileCachePathConstant(): void
    {
        self::assertFalse(defined('PROFILE_CACHE_PATH'));

        $dbPath = $this->createTempDatabasePath();
        $store = new ActivityFlowStore($dbPath, 'ActivityLotterySmoke');
        $flow = $this->createFlow('flow-a', '2026-04-05', 1010);

        $store->save([$flow]);

        $loaded = $store->load('2026-04-05');
        self::assertCount(1, $loaded);
        self::assertSame($flow->toArray(), $loaded[0]->toArray());

        $rows = $this->fetchStoredRows($dbPath);
        self::assertCount(1, $rows);
        self::assertSame('ActivityLotterySmoke', $rows[0]['scope']);
        self::assertSame('2026-04-05', $rows[0]['biz_date']);
        self::assertSame('flow-a', $rows[0]['flow_id']);
    }

    public function testSaveAndLoadRoundTripsMultipleFlowsForSameDay(): void
    {
        $dbPath = $this->createTempDatabasePath();
        $store = new ActivityFlowStore($dbPath, 'ActivityLotterySmoke');

        $first = $this->createFlow('flow-a', '2026-04-05', 1010);
        $second = $this->createFlow('flow-b', '2026-04-05', 2020);

        $store->save([$first, $second]);

        $loaded = $this->indexById($store->load('2026-04-05'));
        self::assertSame(
            [
                'flow-a' => $first->toArray(),
                'flow-b' => $second->toArray(),
            ],
            array_map(
                static fn (ActivityFlow $flow): array => $flow->toArray(),
                $loaded,
            ),
        );

        $rows = $this->fetchStoredRows($dbPath);
        self::assertCount(2, $rows);
    }

    public function testSavingOneSameDayFlowUpsertsWithoutClobberingSiblings(): void
    {
        $dbPath = $this->createTempDatabasePath();
        $store = new ActivityFlowStore($dbPath, 'ActivityLotterySmoke');

        $original = $this->createFlow('flow-a', '2026-04-05', 1010);
        $sibling = $this->createFlow('flow-b', '2026-04-05', 2020);
        $updated = $this->createFlow('flow-a', '2026-04-05', 3030, ['title' => 'updated']);

        $store->save([$original, $sibling]);
        $store->save([$updated]);

        $loaded = $this->indexById($store->load('2026-04-05'));
        self::assertCount(2, $loaded);
        self::assertSame($updated->toArray(), $loaded['flow-a']->toArray());
        self::assertSame($sibling->toArray(), $loaded['flow-b']->toArray());

        $rows = $this->fetchStoredRows($dbPath);
        self::assertCount(2, $rows);
    }

    public function testSameFlowIdOnDifferentBizDatesStaysIsolated(): void
    {
        $dbPath = $this->createTempDatabasePath();
        $store = new ActivityFlowStore($dbPath, 'ActivityLotterySmoke');

        $firstDay = $this->createFlow('flow-a', '2026-04-05', 1010);
        $secondDay = $this->createFlow('flow-a', '2026-04-06', 2020, ['title' => 'day-2']);

        $store->save([$firstDay, $secondDay]);

        $loadedFirstDay = $store->load('2026-04-05');
        $loadedSecondDay = $store->load('2026-04-06');

        self::assertCount(1, $loadedFirstDay);
        self::assertCount(1, $loadedSecondDay);
        self::assertSame($firstDay->toArray(), $loadedFirstDay[0]->toArray());
        self::assertSame($secondDay->toArray(), $loadedSecondDay[0]->toArray());

        $rows = $this->fetchStoredRows($dbPath);
        self::assertCount(2, $rows);
    }

    private function createTempDatabasePath(): string
    {
        $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bilihelper-activity-flow-store-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($root, 0777, true) || is_dir($root));
        $this->tempDirectories[] = $root;

        return $root . DIRECTORY_SEPARATOR . 'cache.sqlite3';
    }

    /**
     * @param array<string, mixed> $activity
     */
    private function createFlow(string $flowId, string $bizDate, int $timestamp, array $activity = []): ActivityFlow
    {
        return new ActivityFlow(
            $flowId,
            $bizDate,
            $activity + ['id' => $flowId, 'title' => 'activity-' . $flowId],
            ActivityFlowStatus::PENDING,
            0,
            [
                new ActivityNode(
                    'load_activity_snapshot',
                    ['flow_id' => $flowId],
                    ActivityNodeStatus::PENDING,
                    ['biz_date' => $bizDate],
                ),
            ],
            0,
            0,
            new ActivityFlowContext(['seed' => $flowId]),
            [
                ['message' => 'created', 'at' => $timestamp],
            ],
            $timestamp,
            $timestamp,
        );
    }

    /**
     * @return array<string, ActivityFlow>
     */
    private function indexById(array $flows): array
    {
        $indexed = [];
        foreach ($flows as $flow) {
            if (!$flow instanceof ActivityFlow) {
                throw new RuntimeException('Unexpected non-ActivityFlow instance');
            }

            $indexed[$flow->id()] = $flow;
        }

        ksort($indexed);

        return $indexed;
    }

    /**
     * @return list<array{scope: string, biz_date: string, flow_id: string, payload_json: string}>
     */
    private function fetchStoredRows(string $dbPath): array
    {
        $db = new SQLite3($dbPath);
        $query = $db->query(sprintf(
            'SELECT scope, biz_date, flow_id, payload_json FROM %s ORDER BY biz_date ASC, flow_id ASC',
            self::TABLE_NAME,
        ));
        self::assertNotFalse($query);

        $rows = [];
        while (true) {
            $row = $query->fetchArray(SQLITE3_ASSOC);
            if (!is_array($row)) {
                break;
            }

            $rows[] = $row;
        }

        $query->finalize();
        $db->close();

        return $rows;
    }

    private function deleteDirectory(string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException('Failed to read temp directory for cleanup: ' . $directory);
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            if (!unlink($path)) {
                throw new RuntimeException('Failed to remove temp file: ' . $path);
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException('Failed to remove temp directory: ' . $directory);
        }
    }
}
