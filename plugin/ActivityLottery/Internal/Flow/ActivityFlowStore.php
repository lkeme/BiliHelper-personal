<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Flow;

use Bhp\Cache\Cache;
use RuntimeException;

final class ActivityFlowStore
{
    private const CACHE_KEY_PREFIX = 'activity_flow_day:';

    public function __construct(private readonly string $scope = 'ActivityLottery')
    {
        if (!defined('PROFILE_CACHE_PATH')) {
            throw new RuntimeException('缺少 PROFILE_CACHE_PATH，无法初始化 ActivityFlowStore');
        }
        Cache::initCache($this->scope);
    }

    /**
     * @param ActivityFlow[] $flows
     *
     * 当前实现采用“整日 blob 读改写”，默认前提是单进程/单写者顺序写入；
     * 不满足该前提时可能发生并发覆盖，后续若引入多写者需升级为 CAS/事务化策略。
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
            $bizDate = $flow->bizDate();
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

        foreach ($grouped as $bizDate => $items) {
            $existingRows = Cache::get($this->cacheKey((string)$bizDate), $this->scope);
            $mergedById = [];
            $storedSeenByDate = [];
            if ($existingRows !== false && !is_array($existingRows)) {
                throw new RuntimeException(sprintf(
                    'ActivityFlowStore::save 读取历史数据失败 biz_date=%s：顶层容器非法',
                    (string)$bizDate,
                ));
            }

            if (is_array($existingRows)) {
                foreach ($existingRows as $index => $row) {
                    if (!is_array($row)) {
                        throw new RuntimeException(sprintf(
                            'ActivityFlowStore::save 读取历史数据失败 biz_date=%s index=%d：行结构非法',
                            (string)$bizDate,
                            (int)$index,
                        ));
                    }
                    try {
                        $restored = ActivityFlow::fromArray($row);
                    } catch (\Throwable $throwable) {
                        $flowId = trim((string)($row['flow_id'] ?? ''));
                        throw new RuntimeException(sprintf(
                            'ActivityFlowStore::save 读取历史数据失败 biz_date=%s index=%d flow_id=%s: %s',
                            (string)$bizDate,
                            (int)$index,
                            $flowId !== '' ? $flowId : '<empty>',
                            $throwable->getMessage(),
                        ), 0, $throwable);
                    }
                    $this->assertFlowBizDateMatchesBucket(
                        $restored,
                        (string)$bizDate,
                        'save',
                        (int)$index,
                    );
                    $this->assertUniqueFlowIdInBucket(
                        $storedSeenByDate,
                        (string)$bizDate,
                        $restored->id(),
                        'save',
                        (int)$index,
                        'stored',
                    );
                    $mergedById[$restored->id()] = $restored->toArray();
                }
            }

            foreach ($items as $flow) {
                $mergedById[$flow->id()] = $flow->toArray();
            }

            Cache::set($this->cacheKey((string)$bizDate), array_values($mergedById), $this->scope);
        }
    }

    /**
     * @return ActivityFlow[]
     */
    public function load(string $bizDate): array
    {
        $normalizedBizDate = ActivityFlow::normalizeBizDate($bizDate);
        $rows = Cache::get($this->cacheKey($normalizedBizDate), $this->scope);
        if ($rows === false) {
            return [];
        }
        if (!is_array($rows)) {
            throw new RuntimeException(sprintf(
                'ActivityFlowStore::load 解析失败 biz_date=%s：顶层容器非法',
                $normalizedBizDate,
            ));
        }

        $flows = [];
        $seenFlowIds = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new RuntimeException(sprintf(
                    'ActivityFlowStore::load 解析失败 biz_date=%s index=%d：行结构非法',
                    $normalizedBizDate,
                    (int)$index,
                ));
            }

            try {
                $flow = ActivityFlow::fromArray($row);
            } catch (\Throwable $throwable) {
                $flowId = trim((string)($row['flow_id'] ?? ''));
                throw new RuntimeException(sprintf(
                    'ActivityFlowStore::load 解析失败 biz_date=%s index=%d flow_id=%s: %s',
                    $normalizedBizDate,
                    (int)$index,
                    $flowId !== '' ? $flowId : '<empty>',
                    $throwable->getMessage(),
                ), 0, $throwable);
            }
            $this->assertFlowBizDateMatchesBucket($flow, $normalizedBizDate, 'load', (int)$index);
            $this->assertUniqueFlowIdInBucket(
                $seenFlowIds,
                $normalizedBizDate,
                $flow->id(),
                'load',
                (int)$index,
                'stored',
            );
            $flows[] = $flow;
        }

        return $flows;
    }

    private function cacheKey(string $bizDate): string
    {
        return self::CACHE_KEY_PREFIX . trim($bizDate);
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
