<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Page;

final class EraPageParser
{
    /**
     * 初始化 EraPageParser
     * @param EraTaskCapabilityResolver $capabilityResolver
     */
    public function __construct(
        private readonly EraTaskCapabilityResolver $capabilityResolver = new EraTaskCapabilityResolver(),
    ) {
    }

    /**
     * 处理解析
     * @param string $html
     * @return ?EraPageSnapshot
     */
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
        $pageFollowTargets = $this->collectFollowTargets($state);
        $pageVideoIds = $this->collectVideoIds($state);
        $pageVideoArchives = $this->collectVideoArchives($state);
        $pageLiveRoomIds = $this->collectLiveRoomIds($state);

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

                    $targetUids = $this->extractTargetUids($taskName, $jumpLink, $task, $pageFollowTargets);
                    $targetVideoIds = $this->extractTargetVideoIds($taskName, $jumpLink, $task, $pageVideoIds);
                    $targetArchives = $this->extractTargetArchives($taskName, $task, $pageVideoArchives);
                    $targetRoomIds = $this->extractTargetRoomIds($taskName, $task, $pageLiveRoomIds);
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
                        'target_archives' => $targetArchives,
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

    /**
     * 处理extractRequired观看Seconds
     * @param string $taskName
     * @param array $task
     * @return int
     */
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

    /**
     * @return array<int, array{uid: string, uname: string, add_lottery_times: bool}>
     */
    private function collectFollowTargets(array $state): array
    {
        $targets = [];
        foreach (['H5FollowNew', 'PcFollowNew'] as $key) {
            foreach (($state[$key] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $uid = trim((string)($item['uid'] ?? ''));
                $uname = trim((string)($item['uname'] ?? ''));
                if ($uid !== '') {
                    $targets[] = [
                        'uid' => $uid,
                        'uname' => $uname,
                        'add_lottery_times' => (bool)($item['addLotteryTimes'] ?? false),
                    ];
                }

                foreach (($item['followUidList'] ?? []) as $follow) {
                    if (!is_array($follow)) {
                        continue;
                    }

                    $followUid = trim((string)($follow['uid'] ?? ''));
                    if ($followUid === '') {
                        continue;
                    }

                    $targets[] = [
                        'uid' => $followUid,
                        'uname' => trim((string)($follow['uname'] ?? '')),
                        'add_lottery_times' => false,
                    ];
                }
            }
        }

        return $this->deduplicateFollowTargets($targets);
    }

    /**
     * @return string[]
     */
    private function collectVideoIds(array $state): array
    {
        $videoIds = [];

        foreach (($state['PcSlidePlayer'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $raw = trim((string)($item['videoIds'] ?? ''));
            if ($raw !== '') {
                $videoIds = array_merge($videoIds, preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            }
        }

        foreach (($state['H5SlideVideos'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $raw = trim((string)($item['aids'] ?? ''));
            if ($raw !== '') {
                $videoIds = array_merge($videoIds, preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            }
        }

        return $this->normalizeStringList($videoIds);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectVideoArchives(array $state): array
    {
        $archives = [];

        foreach (($state['H5SlideVideos'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (($item['videoList'] ?? []) as $video) {
                if (!is_array($video)) {
                    continue;
                }
                $aid = trim((string)($video['aid'] ?? ''));
                $cid = trim((string)($video['cid'] ?? ''));
                if ($aid === '' || $cid === '') {
                    continue;
                }
                $archives[] = [
                    'aid' => $aid,
                    'cid' => $cid,
                    'duration' => (int)($video['duration'] ?? 0),
                    'bvid' => trim((string)($video['bvid'] ?? '')),
                    'title' => trim((string)($video['title'] ?? '')),
                ];
            }
        }

        foreach (($state['PcSlidePlayer'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $videoIds = preg_split('/\s*,\s*/', (string)($item['videoIds'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach (($item['videosDetail'] ?? []) as $index => $video) {
                if (!is_array($video)) {
                    continue;
                }
                $videoId = trim((string)($videoIds[$index] ?? ''));
                $aid = trim((string)($video['aid'] ?? ''));
                $bvid = '';
                if ($videoId !== '') {
                    if (ctype_digit($videoId) && $aid === '') {
                        $aid = $videoId;
                    }
                    if (str_starts_with(strtoupper($videoId), 'BV')) {
                        $bvid = $videoId;
                    }
                }
                if ($aid === '' && $bvid === '') {
                    continue;
                }
                $archives[] = [
                    'aid' => $aid,
                    'bvid' => $bvid,
                    'title' => trim((string)($video['title'] ?? '')),
                ];
            }
        }

        return $this->deduplicateArchives($archives);
    }

    /**
     * @return string[]
     */
    private function collectLiveRoomIds(array $state): array
    {
        $roomIds = [];
        foreach (($state['EraLiveNonRevenuePlayer'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (($item['roomsConfig'] ?? []) as $room) {
                if (!is_array($room)) {
                    continue;
                }
                $roomId = trim((string)($room['roomId'] ?? ''));
                if ($roomId !== '') {
                    $roomIds[] = $roomId;
                }
            }
        }

        return $this->normalizeStringList($roomIds);
    }

    /**
     * 处理extract话题IdFromLink
     * @param string $jumpLink
     * @return string
     */
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

        return trim((string)($params['topic_id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $task
     * @param array<int, array{uid: string, uname: string, add_lottery_times: bool}> $pageFollowTargets
     * @return string[]
     */
    private function extractTargetUids(string $taskName, string $jumpLink, array $task, array $pageFollowTargets): array
    {
        $targetUids = $this->normalizeStringList($task['target_uids'] ?? $task['targetUids'] ?? []);
        if ($targetUids !== []) {
            return $targetUids;
        }

        if (preg_match_all('~space\.bilibili\.com/(\d+)~', $jumpLink, $matches) === 1) {
            return $this->normalizeStringList($matches[1] ?? []);
        }

        if (str_contains($taskName, '关注')) {
            return $this->matchFollowTargetUids($taskName, $pageFollowTargets);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $task
     * @return string[]
     */
    private function extractTargetVideoIds(string $taskName, string $jumpLink, array $task, array $pageVideoIds): array
    {
        $targetVideoIds = $this->normalizeStringList($task['target_video_ids'] ?? $task['targetVideoIds'] ?? []);
        if ($targetVideoIds !== []) {
            return $targetVideoIds;
        }

        if (preg_match('~/(BV[0-9A-Za-z]+)~', $jumpLink, $matches) === 1) {
            return [$matches[1]];
        }

        if (str_contains($taskName, '观看') && !str_contains($taskName, '直播')) {
            return $pageVideoIds;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $task
     * @param array<int, array<string, mixed>> $pageVideoArchives
     * @return array<int, array<string, mixed>>
     */
    private function extractTargetArchives(string $taskName, array $task, array $pageVideoArchives): array
    {
        $targetArchives = $this->normalizeArchiveList($task['target_archives'] ?? $task['targetArchives'] ?? []);
        if ($targetArchives !== []) {
            return $targetArchives;
        }

        if (str_contains($taskName, '观看') && !str_contains($taskName, '直播')) {
            return $pageVideoArchives;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $task
     * @return string[]
     */
    private function extractTargetRoomIds(string $taskName, array $task, array $pageLiveRoomIds): array
    {
        $roomIds = $this->normalizeStringList($task['target_room_ids'] ?? $task['targetRoomIds'] ?? []);
        if ($roomIds !== []) {
            return $roomIds;
        }

        if (str_contains($taskName, '直播')) {
            return $pageLiveRoomIds;
        }

        return [];
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
     * @return array<int, array<string, mixed>>
     */
    private function normalizeArchiveList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $archives = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $archives[] = $item;
            }
        }

        return $archives;
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
     * @param array<int, array{uid: string, uname: string, add_lottery_times: bool}> $targets
     * @return string[]
     */
    private function matchFollowTargetUids(string $taskName, array $targets): array
    {
        $matched = [];
        foreach ($targets as $target) {
            $name = trim((string)($target['uname'] ?? ''));
            if ($name !== '' && str_contains($taskName, $name)) {
                $matched[] = (string)($target['uid'] ?? '');
            }
        }

        $matched = $this->normalizeStringList($matched);
        if ($matched !== []) {
            return $matched;
        }

        $lotteryTargets = array_values(array_filter(
            $targets,
            static fn (array $target): bool => (bool)($target['add_lottery_times'] ?? false)
        ));
        if (count($lotteryTargets) === 1) {
            return [(string)$lotteryTargets[0]['uid']];
        }
        if (count($targets) === 1) {
            return [(string)$targets[0]['uid']];
        }

        return [];
    }

    /**
     * @param array<int, array{uid: string, uname: string, add_lottery_times: bool}> $targets
     * @return array<int, array{uid: string, uname: string, add_lottery_times: bool}>
     */
    private function deduplicateFollowTargets(array $targets): array
    {
        $unique = [];
        foreach ($targets as $target) {
            $uid = trim((string)($target['uid'] ?? ''));
            if ($uid === '') {
                continue;
            }

            if (!isset($unique[$uid])) {
                $unique[$uid] = [
                    'uid' => $uid,
                    'uname' => trim((string)($target['uname'] ?? '')),
                    'add_lottery_times' => (bool)($target['add_lottery_times'] ?? false),
                ];
                continue;
            }

            if (($target['add_lottery_times'] ?? false) && !$unique[$uid]['add_lottery_times']) {
                $unique[$uid]['add_lottery_times'] = true;
                $unique[$uid]['uname'] = trim((string)($target['uname'] ?? $unique[$uid]['uname']));
            }
        }

        return array_values($unique);
    }

    /**
     * @param array<int, array<string, mixed>> $archives
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateArchives(array $archives): array
    {
        $unique = [];
        foreach ($archives as $archive) {
            $aid = trim((string)($archive['aid'] ?? ''));
            $bvid = trim((string)($archive['bvid'] ?? ''));
            $key = $aid !== '' ? 'aid:' . $aid : ($bvid !== '' ? 'bvid:' . $bvid : '');
            if ($key === '') {
                continue;
            }
            if (!isset($unique[$key])) {
                $unique[$key] = $archive;
            }
        }

        return array_values($unique);
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

    /**
     * 处理extractJSONObject
     * @param string $source
     * @param int $start
     * @return ?string
     */
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

    /**
     * 处理extractNested
     * @param mixed $value
     * @param array $segments
     * @param int $offset
     * @return mixed
     */
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

