<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Page;

final class EraPageParser
{
    public function __construct(
        private readonly EraTaskCapabilityResolver $capabilityResolver = new EraTaskCapabilityResolver(),
    ) {
    }

    public function parse(string $html): ?EraPageSnapshot
    {
        $initialState = $this->extractAssignedJson($html, 'window.__initialState');
        if ($initialState === null) {
            return null;
        }

        $pageInfo = $this->extractAssignedJson($html, 'window.__BILIACT_PAGEINFO__') ?? [];

        $activityId = trim((string)(
            $this->extractNested($initialState, ['EraLottery', 'config', 'activity_id'])
            ?? $this->extractNested($initialState, ['EraLotteryPc', 'config', 'activity_id'])
            ?? ($pageInfo['activity_id'] ?? '')
        ));
        $lotteryId = trim((string)(
            $this->extractNested($initialState, ['EraLottery', 'config', 'lottery_id'])
            ?? $this->extractNested($initialState, ['EraLotteryPc', 'config', 'lottery_id'])
            ?? ''
        ));
        $startTime = (int)(
            $this->extractNested($initialState, ['BaseInfo', 'start_time'])
            ?? $this->extractNested($initialState, ['BaseInfo', 'startTime'])
            ?? ($pageInfo['start_time'] ?? 0)
        );
        $endTime = (int)(
            $this->extractNested($initialState, ['BaseInfo', 'end_time'])
            ?? $this->extractNested($initialState, ['BaseInfo', 'endTime'])
            ?? ($pageInfo['end_time'] ?? 0)
        );

        return EraPageSnapshot::fromArray([
            'title' => trim((string)($pageInfo['title'] ?? $this->extractNested($initialState, ['BaseInfo', 'title']) ?? '')),
            'page_id' => trim((string)($pageInfo['page_id'] ?? '')),
            'activity_id' => $activityId,
            'lottery_id' => $lotteryId,
            'start_time' => max(0, $startTime),
            'end_time' => max(0, $endTime),
            'tasks' => $this->extractTasks($initialState),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractTasks(array $state): array
    {
        $tasks = [];
        foreach (['EraTasklist', 'EraTasklistPc'] as $componentKey) {
            foreach (($state[$componentKey] ?? []) as $component) {
                if (!is_array($component)) {
                    continue;
                }

                foreach (($component['tasklist'] ?? []) as $task) {
                    if (!is_array($task)) {
                        continue;
                    }

                    $taskName = trim((string)($task['taskName'] ?? $task['task_name'] ?? ''));
                    $jumpLink = trim((string)($task['jumpLink'] ?? $task['jump_link'] ?? ''));
                    $topicId = trim((string)($task['topicID'] ?? $task['topicId'] ?? $task['topic_id'] ?? ''));
                    if ($topicId === '') {
                        $topicId = $this->extractTopicIdFromLink($jumpLink);
                    }
                    $targetUids = $this->extractTargetUids($jumpLink, $task);
                    $targetVideoIds = $this->extractTargetVideoIds($jumpLink, $task);
                    $targetRoomIds = $this->extractTargetRoomIds($task);
                    $liveAreaTarget = $this->extractLiveAreaTarget($jumpLink, $task);

                    $taskRow = [
                        'task_id' => trim((string)($task['taskId'] ?? $task['task_id'] ?? '')),
                        'task_name' => $taskName,
                        'task_status' => (int)($task['taskStatus'] ?? $task['task_status'] ?? 0),
                        'task_award_type' => (int)($task['taskAwardType'] ?? $task['task_award_type'] ?? 0),
                        'counter' => trim((string)($task['counter'] ?? '')),
                        'jump_link' => $jumpLink,
                        'topic_id' => $topicId,
                        'award_name' => trim((string)($task['awardName'] ?? $task['award_name'] ?? '')),
                        'required_watch_seconds' => $this->extractRequiredWatchSeconds($taskName, $task),
                        'target_uids' => $targetUids,
                        'target_video_ids' => $targetVideoIds,
                        'target_room_ids' => $targetRoomIds,
                        'target_area_id' => $liveAreaTarget['area_id'],
                        'target_parent_area_id' => $liveAreaTarget['parent_area_id'],
                        'checkpoints' => $this->normalizeCheckpointList($task['checkpoints'] ?? []),
                        'btn_behavior' => $this->normalizeBehavior($task['btnBehavior'] ?? $task['btn_behavior'] ?? []),
                    ];
                    $capability = $this->capabilityResolver->resolve($taskRow);
                    $taskRow['capability'] = $capability;
                    $taskRow['support_level'] = $this->capabilityResolver->resolveSupportLevel($taskRow, $capability);
                    $tasks[] = $taskRow;
                }
            }
        }

        return $tasks;
    }

    private function extractRequiredWatchSeconds(string $taskName, array $task): int
    {
        $explicit = (int)($task['requiredWatchSeconds'] ?? $task['required_watch_seconds'] ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        if (preg_match('/(\d+)\s*分钟/u', $taskName, $matches) === 1) {
            return max(0, (int)$matches[1]) * 60;
        }
        if (preg_match('/(\d+)\s*秒/u', $taskName, $matches) === 1) {
            return max(0, (int)$matches[1]);
        }

        return 0;
    }

    private function extractTopicIdFromLink(string $jumpLink): string
    {
        $query = parse_url(html_entity_decode($jumpLink, ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        if (!is_array($params)) {
            return '';
        }

        $topicId = trim((string)($params['topic_id'] ?? ''));
        return $topicId;
    }

    /**
     * @param array<string, mixed> $task
     * @return string[]
     */
    private function extractTargetUids(string $jumpLink, array $task): array
    {
        $targetUids = $this->normalizeStringList($task['target_uids'] ?? $task['targetUids'] ?? []);
        if ($targetUids !== []) {
            return $targetUids;
        }

        if (preg_match_all('~space\.bilibili\.com/(\d+)~', $jumpLink, $matches) !== false) {
            return $this->normalizeStringList($matches[1] ?? []);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $task
     * @return string[]
     */
    private function extractTargetVideoIds(string $jumpLink, array $task): array
    {
        $targetVideoIds = $this->normalizeStringList($task['target_video_ids'] ?? $task['targetVideoIds'] ?? []);
        if ($targetVideoIds !== []) {
            return $targetVideoIds;
        }

        if (preg_match('~/(BV[0-9A-Za-z]+)~', $jumpLink, $matches) === 1) {
            return [$matches[1]];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $task
     * @return string[]
     */
    private function extractTargetRoomIds(array $task): array
    {
        return $this->normalizeStringList($task['target_room_ids'] ?? $task['targetRoomIds'] ?? []);
    }

    /**
     * @param array<string, mixed> $task
     * @return array{area_id: int, parent_area_id: int}
     */
    private function extractLiveAreaTarget(string $jumpLink, array $task): array
    {
        $explicitAreaId = (int)($task['target_area_id'] ?? $task['targetAreaId'] ?? 0);
        $explicitParentAreaId = (int)($task['target_parent_area_id'] ?? $task['targetParentAreaId'] ?? 0);
        if ($explicitAreaId > 0 || $explicitParentAreaId > 0) {
            return [
                'area_id' => max(0, $explicitAreaId),
                'parent_area_id' => max(0, $explicitParentAreaId),
            ];
        }

        $query = parse_url(html_entity_decode($jumpLink, ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return ['area_id' => 0, 'parent_area_id' => 0];
        }

        parse_str($query, $params);
        if (!is_array($params)) {
            return ['area_id' => 0, 'parent_area_id' => 0];
        }

        $areaId = (int)($params['areaId'] ?? $params['second_area_id'] ?? $params['area_id'] ?? 0);
        $parentAreaId = (int)($params['parentAreaId'] ?? $params['parent_area_id'] ?? 0);
        if ($parentAreaId <= 0 && isset($params['second_area_id'])) {
            $parentAreaId = (int)($params['area_id'] ?? 0);
        }

        return [
            'area_id' => max(0, $areaId),
            'parent_area_id' => max(0, $parentAreaId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCheckpointList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $checkpoints = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $checkpoints[] = $item;
            }
        }

        return $checkpoints;
    }

    /**
     * @return string[]
     */
    private function normalizeBehavior(mixed $value): array
    {
        $normalized = $this->normalizeStringList($value);
        return array_values(array_unique(array_map(static fn (string $label): string => strtoupper($label), $normalized)));
    }

    /**
     * @return string[]
     */
    private function normalizeStringList(mixed $value): array
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

            $normalized[] = $label;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractAssignedJson(string $html, string $assignment): ?array
    {
        $position = strpos($html, $assignment);
        if ($position === false) {
            return null;
        }

        $start = strpos($html, '{', $position);
        if ($start === false) {
            return null;
        }

        $json = $this->extractJsonObject($html, $start);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function extractJsonObject(string $source, int $start): ?string
    {
        $length = strlen($source);
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $source[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char !== '}') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }

        return null;
    }

    private function extractNested(mixed $value, array $segments, int $offset = 0): mixed
    {
        if ($offset >= count($segments)) {
            return $value;
        }
        if (!is_array($value)) {
            return null;
        }

        $segment = $segments[$offset];
        if (array_key_exists($segment, $value)) {
            return $this->extractNested($value[$segment], $segments, $offset + 1);
        }

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nested = $this->extractNested($item, $segments, $offset);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }
}
