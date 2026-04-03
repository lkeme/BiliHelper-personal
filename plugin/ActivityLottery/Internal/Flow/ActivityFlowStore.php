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
     */
    public function save(array $flows): void
    {
        $grouped = [];
        foreach ($flows as $flow) {
            $grouped[$flow->bizDate()][] = $flow;
        }

        foreach ($grouped as $bizDate => $items) {
            $existingRows = Cache::get($this->cacheKey((string)$bizDate), $this->scope);
            $mergedById = [];
            if (is_array($existingRows)) {
                foreach ($existingRows as $index => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $flowId = trim((string)($row['flow_id'] ?? ''));
                    if ($flowId === '') {
                        $flowId = '__legacy_' . $index;
                    }
                    $mergedById[$flowId] = $row;
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
        $rows = Cache::get($this->cacheKey($bizDate), $this->scope);
        if (!is_array($rows)) {
            return [];
        }

        $flows = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $flows[] = ActivityFlow::fromArray($row);
            }
        }

        return $flows;
    }

    private function cacheKey(string $bizDate): string
    {
        return self::CACHE_KEY_PREFIX . trim($bizDate);
    }
}
