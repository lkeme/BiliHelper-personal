<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Page;

final class EraTaskCapabilityResolver
{
    public const SUPPORT_NOW = 'now';
    public const SUPPORT_LATER = 'later';
    public const SUPPORT_MANUAL = 'manual';

    public const CAPABILITY_CLAIM_REWARD = 'claim_reward';
    public const CAPABILITY_SHARE = 'share';
    public const CAPABILITY_FOLLOW = 'follow';
    public const CAPABILITY_WATCH_VIDEO_FIXED = 'watch_video_fixed';
    public const CAPABILITY_WATCH_VIDEO_TOPIC = 'watch_video_topic';
    public const CAPABILITY_WATCH_LIVE = 'watch_live';
    public const CAPABILITY_COIN_TOPIC = 'coin_topic';
    public const CAPABILITY_LIKE_TOPIC = 'like_topic';
    public const CAPABILITY_COMMENT_TOPIC = 'comment_topic';
    public const CAPABILITY_MANUAL = 'manual_only';
    public const CAPABILITY_UNKNOWN = 'unknown';

    /**
     * @param array<string, mixed> $task
     */
    public function resolve(array $task): string
    {
        $explicitCapability = trim((string)($task['capability'] ?? ''));
        if ($explicitCapability !== '') {
            return $explicitCapability;
        }

        $taskName = trim((string)($task['task_name'] ?? $task['taskName'] ?? ''));
        $taskStatus = (int)($task['task_status'] ?? $task['taskStatus'] ?? 0);
        $taskAwardType = (int)($task['task_award_type'] ?? $task['taskAwardType'] ?? 0);
        $topicId = trim((string)($task['topic_id'] ?? $task['topicId'] ?? $task['topicID'] ?? ''));
        $btnBehavior = $this->normalizeBehavior($task['btn_behavior'] ?? $task['btnBehavior'] ?? []);

        if ($taskAwardType === 1 && $taskStatus === 2) {
            return self::CAPABILITY_CLAIM_REWARD;
        }

        if (str_contains($taskName, '分享')) {
            return self::CAPABILITY_SHARE;
        }
        if (str_contains($taskName, '关注')) {
            return self::CAPABILITY_FOLLOW;
        }
        if (str_contains($taskName, '直播') && str_contains($taskName, '观看')) {
            return self::CAPABILITY_WATCH_LIVE;
        }
        if (str_contains($taskName, '观看')) {
            if ($topicId !== '') {
                return self::CAPABILITY_WATCH_VIDEO_TOPIC;
            }

            return self::CAPABILITY_WATCH_VIDEO_FIXED;
        }
        if (str_contains($taskName, '投币')) {
            return self::CAPABILITY_COIN_TOPIC;
        }
        if (str_contains($taskName, '点赞')) {
            return self::CAPABILITY_LIKE_TOPIC;
        }
        if (str_contains($taskName, '评论')) {
            return self::CAPABILITY_COMMENT_TOPIC;
        }
        if (in_array('SCROLL', $btnBehavior, true)) {
            return self::CAPABILITY_MANUAL;
        }
        if (
            str_contains($taskName, '投稿')
            || str_contains($taskName, '开播')
            || str_contains($taskName, '下载')
            || str_contains($taskName, '签到')
            || str_contains($taskName, '讨论')
            || str_contains($taskName, '榜单')
        ) {
            return self::CAPABILITY_MANUAL;
        }

        return self::CAPABILITY_UNKNOWN;
    }

    /**
     * @param array<string, mixed> $task
     */
    public function resolveSupportLevel(array $task, ?string $capability = null): string
    {
        $capability ??= $this->resolve($task);

        if (in_array($capability, [
            self::CAPABILITY_MANUAL,
            self::CAPABILITY_COIN_TOPIC,
            self::CAPABILITY_LIKE_TOPIC,
            self::CAPABILITY_COMMENT_TOPIC,
        ], true)) {
            return self::SUPPORT_MANUAL;
        }

        if (in_array($capability, [
            self::CAPABILITY_CLAIM_REWARD,
            self::CAPABILITY_SHARE,
        ], true)) {
            return self::SUPPORT_NOW;
        }

        $topicId = trim((string)($task['topic_id'] ?? $task['topicId'] ?? $task['topicID'] ?? ''));
        $targetUids = $this->normalizeBehavior($task['target_uids'] ?? $task['targetUids'] ?? []);
        $targetVideoIds = $this->normalizeBehavior($task['target_video_ids'] ?? $task['targetVideoIds'] ?? []);
        $targetRoomIds = $this->normalizeBehavior($task['target_room_ids'] ?? $task['targetRoomIds'] ?? []);
        $targetAreaId = (int)($task['target_area_id'] ?? $task['targetAreaId'] ?? 0);

        if ($capability === self::CAPABILITY_FOLLOW) {
            return $targetUids !== [] ? self::SUPPORT_NOW : self::SUPPORT_LATER;
        }

        if ($capability === self::CAPABILITY_WATCH_VIDEO_FIXED) {
            return $targetVideoIds !== [] ? self::SUPPORT_NOW : self::SUPPORT_LATER;
        }

        if ($capability === self::CAPABILITY_WATCH_VIDEO_TOPIC) {
            return ($targetVideoIds !== [] || $topicId !== '')
                ? self::SUPPORT_NOW
                : self::SUPPORT_LATER;
        }

        if ($capability === self::CAPABILITY_WATCH_LIVE) {
            return ($targetRoomIds !== [] || $targetAreaId > 0)
                ? self::SUPPORT_NOW
                : self::SUPPORT_LATER;
        }

        return self::SUPPORT_LATER;
    }

    /**
     * @return string[]
     */
    private function normalizeBehavior(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item)) {
                continue;
            }

            $label = trim((string)$item);
            if ($label === '') {
                continue;
            }

            $normalized[] = strtoupper($label);
        }

        return array_values(array_unique($normalized));
    }
}

