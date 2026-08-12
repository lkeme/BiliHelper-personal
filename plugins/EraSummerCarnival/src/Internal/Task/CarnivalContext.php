<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Task;

use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Page\CarnivalSnapshot;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\TaskStatus;

/**
 * 单次 tick 的共享上下文。
 *
 * taskDetails 来自一次 totalv2 批量查询，是所有 runner 幂等判断的唯一数据源。
 */
final class CarnivalContext
{
    /**
     * @param array<string, array<string, mixed>> $taskDetails taskId => totalv2 单项
     */
    public function __construct(
        public readonly CarnivalSnapshot $snapshot,
        public readonly array $taskDetails,
        public readonly string $bizDate,
        public readonly int $now,
    ) {
    }

    /**
     * 任务状态：1 可执行 / 2 可领奖 / 3 已完结 / 0 未知
     */
    public function taskStatus(string $taskId): int
    {
        return TaskStatus::of($this->taskDetails[$taskId]['task_status'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function taskDetail(string $taskId): array
    {
        $detail = $this->taskDetails[$taskId] ?? null;

        return is_array($detail) ? $detail : [];
    }

    /**
     * 任务当前进度值（indicators[0].cur_value）
     */
    public function taskCurrentValue(string $taskId): int
    {
        $indicators = $this->taskDetail($taskId)['indicators'] ?? null;
        if (!is_array($indicators) || !is_array($indicators[0] ?? null)) {
            return 0;
        }

        return max(0, (int)($indicators[0]['cur_value'] ?? 0));
    }

    /**
     * 任务目标值（indicators[0].limit）
     */
    public function taskLimit(string $taskId): int
    {
        $indicators = $this->taskDetail($taskId)['indicators'] ?? null;
        if (!is_array($indicators) || !is_array($indicators[0] ?? null)) {
            return 0;
        }

        return max(0, (int)($indicators[0]['limit'] ?? 0));
    }

    /**
     * 是否已拿到任务详情（totalv2 未返回该任务时为 false）
     */
    public function hasTaskDetail(string $taskId): bool
    {
        return isset($this->taskDetails[$taskId]) && is_array($this->taskDetails[$taskId]);
    }
}
