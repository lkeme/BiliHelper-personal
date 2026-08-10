<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Runtime;

use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\CarnivalIds;

/**
 * 与 ActivityLottery 的接管冲突自守卫。
 *
 * 该活动原本收录在 resources/plugins/ActivityLottery/catalog.json 的 data 中。
 * 若交接（把 URL 加入 ignore_urls 并重跑 ActivityInfoUpdate）尚未完成，
 * 而 ActivityLottery 又处于启用状态，两个插件会同时处理同一活动：
 *   - 互相取关对方刚关注的 UP 主
 *   - 重复消耗同一 lottery_id 的抽奖次数
 *
 * 判定条件必须同时成立才算冲突 —— 只看 catalog 会在 ActivityLottery 关闭时误挡本插件。
 */
final class CatalogConflictGuard
{
    private const CATALOG_RELATIVE_PATH = 'resources/plugins/ActivityLottery/catalog.json';

    public function __construct(
        private readonly string $appRoot,
        private readonly bool $activityLotteryEnabled,
    ) {
    }

    /**
     * 是否存在接管冲突
     */
    public function hasConflict(): bool
    {
        if (!$this->activityLotteryEnabled) {
            return false;
        }

        return $this->catalogContainsActivity();
    }

    /**
     * 冲突说明，用于日志
     */
    public function describe(): string
    {
        return sprintf(
            'ActivityLottery 已启用且该活动仍在其 catalog.json 中；请先把 %s 加入 ignore_urls 并重跑 mode:script -p ActivityInfoUpdate 完成交接',
            CarnivalIds::ACTIVITY_URL,
        );
    }

    /**
     * catalog 的 data 中是否仍包含该活动
     */
    private function catalogContainsActivity(): bool
    {
        $path = rtrim(str_replace('\\', '/', $this->appRoot), '/') . '/' . self::CATALOG_RELATIVE_PATH;
        if (!is_file($path)) {
            return false;
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return false;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $activityId = trim((string)($item['activity_id'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            if ($activityId !== '' && $activityId === CarnivalIds::ACTIVITY_ID) {
                return true;
            }
            if ($url !== '' && $url === CarnivalIds::ACTIVITY_URL) {
                return true;
            }
        }

        return false;
    }
}
