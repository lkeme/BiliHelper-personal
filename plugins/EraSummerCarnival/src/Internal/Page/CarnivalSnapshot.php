<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Page;

use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\CarnivalIds;

/**
 * 活动配置快照。
 *
 * 由 CarnivalPageResolver 从活动页解析产出，可序列化后缓存。
 */
final class CarnivalSnapshot
{
    /**
     * @param array<string, array{days: int, reward_name: string}> $signInCheckpoints
     * @param array<string, array{counter: string, task_name: string}> $followTasks
     * @param int[] $followTargets
     * @param string[] $fallbackFields 使用了硬编码兜底的字段名
     */
    public function __construct(
        public readonly string $activityId,
        public readonly string $activityName,
        public readonly string $pageId,
        public readonly string $lotteryId,
        public readonly string $activityUrl,
        public readonly string $liveSourceId,
        public readonly string $signInTaskId,
        public readonly string $signInCounter,
        public readonly string $signInTaskName,
        public readonly array $signInCheckpoints,
        public readonly string $watchLiveTaskId,
        public readonly string $watchLiveCounter,
        public readonly int $watchLiveTargetSeconds,
        public readonly array $followTasks,
        public readonly array $followTargets,
        public readonly array $fallbackFields,
        public readonly int $resolvedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            trim((string)($data['activity_id'] ?? '')),
            trim((string)($data['activity_name'] ?? '')),
            trim((string)($data['page_id'] ?? '')),
            trim((string)($data['lottery_id'] ?? '')),
            trim((string)($data['activity_url'] ?? '')),
            trim((string)($data['live_source_id'] ?? '')),
            trim((string)($data['sign_in_task_id'] ?? '')),
            trim((string)($data['sign_in_counter'] ?? '')),
            trim((string)($data['sign_in_task_name'] ?? '')),
            self::normalizeCheckpoints($data['sign_in_checkpoints'] ?? []),
            trim((string)($data['watch_live_task_id'] ?? '')),
            trim((string)($data['watch_live_counter'] ?? '')),
            max(1, (int)($data['watch_live_target_seconds'] ?? CarnivalIds::WATCH_LIVE_TARGET_SECONDS)),
            self::normalizeFollowTasks($data['follow_tasks'] ?? []),
            self::normalizeTargets($data['follow_targets'] ?? []),
            array_values(array_filter((array)($data['fallback_fields'] ?? []), 'is_string')),
            max(0, (int)($data['resolved_at'] ?? 0)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'activity_id' => $this->activityId,
            'activity_name' => $this->activityName,
            'page_id' => $this->pageId,
            'lottery_id' => $this->lotteryId,
            'activity_url' => $this->activityUrl,
            'live_source_id' => $this->liveSourceId,
            'sign_in_task_id' => $this->signInTaskId,
            'sign_in_counter' => $this->signInCounter,
            'sign_in_task_name' => $this->signInTaskName,
            'sign_in_checkpoints' => $this->signInCheckpoints,
            'watch_live_task_id' => $this->watchLiveTaskId,
            'watch_live_counter' => $this->watchLiveCounter,
            'watch_live_target_seconds' => $this->watchLiveTargetSeconds,
            'follow_tasks' => $this->followTasks,
            'follow_targets' => $this->followTargets,
            'fallback_fields' => $this->fallbackFields,
            'resolved_at' => $this->resolvedAt,
        ];
    }

    /**
     * 抽奖所需的最小上下文，供 ApiActivity::doLottery/myTimes 使用
     *
     * @return array<string, string>
     */
    public function lotteryInfo(): array
    {
        return [
            'sid' => $this->lotteryId,
            'page_id' => $this->pageId,
            'url' => $this->activityUrl,
        ];
    }

    /**
     * 需要批量查询进度的任务 ID
     *
     * @return string[]
     */
    public function trackedTaskIds(): array
    {
        $ids = [];
        if ($this->signInTaskId !== '') {
            $ids[] = $this->signInTaskId;
        }
        if ($this->watchLiveTaskId !== '') {
            $ids[] = $this->watchLiveTaskId;
        }
        foreach (array_keys($this->followTasks) as $taskId) {
            $ids[] = (string)$taskId;
        }

        return array_values(array_unique(array_filter($ids, static fn (string $id): bool => $id !== '')));
    }

    /**
     * 判断关键字段是否齐备
     * @return bool
     */
    public function isUsable(): bool
    {
        return $this->activityId !== ''
            && $this->lotteryId !== ''
            && $this->signInTaskId !== ''
            && $this->signInCounter !== '';
    }

    /**
     * @param array<string, array{days: int, reward_name: string}> $checkpoints
     * @return array<string, array{days: int, reward_name: string}>
     */
    private static function normalizeCheckpoints(mixed $checkpoints): array
    {
        if (!is_array($checkpoints)) {
            return [];
        }

        $normalized = [];
        foreach ($checkpoints as $sid => $item) {
            $sid = trim((string)$sid);
            if ($sid === '' || !is_array($item)) {
                continue;
            }

            $normalized[$sid] = [
                'days' => max(0, (int)($item['days'] ?? 0)),
                'reward_name' => trim((string)($item['reward_name'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, array{counter: string, task_name: string}>
     */
    private static function normalizeFollowTasks(mixed $tasks): array
    {
        if (!is_array($tasks)) {
            return [];
        }

        $normalized = [];
        foreach ($tasks as $taskId => $item) {
            $taskId = trim((string)$taskId);
            if ($taskId === '' || !is_array($item)) {
                continue;
            }

            $normalized[$taskId] = [
                'counter' => trim((string)($item['counter'] ?? '')),
                'task_name' => trim((string)($item['task_name'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @return int[]
     */
    private static function normalizeTargets(mixed $targets): array
    {
        if (!is_array($targets)) {
            return [];
        }

        $normalized = [];
        foreach ($targets as $target) {
            if (!is_numeric($target)) {
                continue;
            }

            $mid = (int)$target;
            if ($mid > 0) {
                $normalized[] = $mid;
            }
        }

        return array_values(array_unique($normalized));
    }
}
