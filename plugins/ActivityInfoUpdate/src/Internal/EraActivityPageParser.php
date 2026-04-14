<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityInfoUpdate\Internal;

final class EraActivityPageParser
{
    /**
     * 处理解析
     * @param string $html
     * @return ?EraActivityPage
     */
    public function parse(string $html): ?EraActivityPage
    {
        $initialState = $this->extractAssignedJson($html, 'window.__initialState');
        if ($initialState === null) {
            return null;
        }

        $pageInfo = $this->extractAssignedJson($html, 'window.__BILIACT_PAGEINFO__') ?? [];

        $topicIds = $this->collectTopicIds($initialState);
        $followTargets = $this->collectFollowTargets($initialState);
        $followUids = array_values(array_unique(array_map(static fn(EraActivityFollowTarget $target): string => $target->uid, $followTargets)));
        $videoIds = $this->collectVideoIds($initialState);
        $videoArchives = $this->collectVideoArchives($initialState);
        $liveRoomIds = $this->collectLiveRoomIds($initialState);
        $activityId = $this->collectFirstNonEmpty($initialState, [['EraLottery', 'config', 'activity_id'], ['EraLotteryPc', 'config', 'activity_id']])
            ?: (string)($pageInfo['activity_id'] ?? '');
        $lotteryId = $this->collectFirstNonEmpty($initialState, [['EraLottery', 'config', 'lottery_id'], ['EraLotteryPc', 'config', 'lottery_id']]);

        $tasks = $this->collectTasks($initialState, $topicIds, $followTargets, $videoIds, $videoArchives, $liveRoomIds);

        return new EraActivityPage(
            (string)($pageInfo['title'] ?? (($initialState['BaseInfo']['title'] ?? ''))),
            (string)($pageInfo['page_id'] ?? ''),
            (string)$activityId,
            (string)$lotteryId,
            0,
            0,
            $topicIds,
            $followUids,
            $followTargets,
            $videoIds,
            $liveRoomIds,
            $tasks,
        );
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

        for ($index = $start; $index < $length; $index++) {
            $char = $source[$index];

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
                return substr($source, $start, $index - $start + 1);
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function collectTopicIds(array $state): array
    {
        $topicIds = [];
        foreach (['EraVideoSource', 'EraVideoSourcePc'] as $key) {
            foreach (($state[$key] ?? []) as $item) {
                $topicId = (string)($item['config']['topic_id'] ?? '');
                if ($topicId !== '') {
                    $topicIds[] = $topicId;
                }
            }
        }

        return $this->normalizeStringArray($topicIds);
    }

    /**
     * @return EraActivityFollowTarget[]
     */
    private function collectFollowTargets(array $state): array
    {
        $targets = [];
        foreach (['H5FollowNew', 'PcFollowNew'] as $key) {
            foreach (($state[$key] ?? []) as $item) {
                $uid = (string)($item['uid'] ?? '');
                $uname = trim((string)($item['uname'] ?? ''));
                if ($uid !== '') {
                    $targets[] = new EraActivityFollowTarget(
                        $uid,
                        $uname,
                        (bool)($item['addLotteryTimes'] ?? false),
                    );
                }

                foreach (($item['followUidList'] ?? []) as $follow) {
                    $followUid = (string)($follow['uid'] ?? '');
                    if ($followUid !== '') {
                        $targets[] = new EraActivityFollowTarget(
                            $followUid,
                            trim((string)($follow['uname'] ?? '')),
                            false,
                        );
                    }
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
            $raw = (string)($item['videoIds'] ?? '');
            if ($raw === '') {
                continue;
            }

            $videoIds = array_merge($videoIds, preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        }

        foreach (($state['H5SlideVideos'] ?? []) as $item) {
            $raw = (string)($item['aids'] ?? '');
            if ($raw === '') {
                continue;
            }

            $videoIds = array_merge($videoIds, preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        }

        foreach (($state['PcVideoCard'] ?? []) as $item) {
            foreach (($item['videoConfig']['videoList'] ?? []) as $video) {
                $videoId = (string)($video['videoID'] ?? '');
                if ($videoId !== '') {
                    $videoIds[] = $videoId;
                }
            }
        }

        foreach (($state['YunqueVideoCard'] ?? []) as $item) {
            $videoId = (string)($item['bvid'] ?? '');
            if ($videoId !== '') {
                $videoIds[] = $videoId;
            }
        }

        foreach (($state['EvaVideoPlayerH5'] ?? []) as $item) {
            $videoId = (string)($item['videoId'] ?? '');
            if ($videoId !== '') {
                $videoIds[] = $videoId;
            }
        }

        return $this->normalizeStringArray($videoIds);
    }

    /**
     * @return string[]
     */
    private function collectLiveRoomIds(array $state): array
    {
        $roomIds = [];
        foreach (($state['EraLiveNonRevenuePlayer'] ?? []) as $item) {
            foreach (($item['roomsConfig'] ?? []) as $room) {
                $roomId = (string)($room['roomId'] ?? '');
                if ($roomId !== '') {
                    $roomIds[] = $roomId;
                }
            }
        }

        return $this->normalizeStringArray($roomIds);
    }

    /**
     * @param string[] $pageTopicIds
     * @param EraActivityFollowTarget[] $pageFollowTargets
     * @param string[] $pageVideoIds
     * @param array<int, array<string, mixed>> $pageVideoArchives
     * @param string[] $pageLiveRoomIds
     * @return EraActivityTask[]
     */
    private function collectTasks(
        array $state,
        array $pageTopicIds,
        array $pageFollowTargets,
        array $pageVideoIds,
        array $pageVideoArchives,
        array $pageLiveRoomIds,
    ): array {
        $tasks = [];
        foreach (['EraTasklist', 'EraTasklistPc'] as $key) {
            foreach (($state[$key] ?? []) as $component) {
                foreach (($component['tasklist'] ?? []) as $task) {
                    if (!is_array($task)) {
                        continue;
                    }

                    $tasks[] = $this->buildTask($task, $pageTopicIds, $pageFollowTargets, $pageVideoIds, $pageVideoArchives, $pageLiveRoomIds);
                }
            }
        }

        return $tasks;
    }

    /**
     * @param string[] $pageTopicIds
     * @param EraActivityFollowTarget[] $pageFollowTargets
     * @param string[] $pageVideoIds
     * @param array<int, array<string, mixed>> $pageVideoArchives
     * @param string[] $pageLiveRoomIds
     */
    private function buildTask(
        array $task,
        array $pageTopicIds,
        array $pageFollowTargets,
        array $pageVideoIds,
        array $pageVideoArchives,
        array $pageLiveRoomIds,
    ): EraActivityTask {
        $taskName = (string)($task['taskName'] ?? '');
        $btnBehavior = $this->normalizeStringArray($task['btnBehavior'] ?? []);
        $jumpLink = (string)($task['jumpLink'] ?? '');
        $topicId = (string)($task['topicID'] ?? '');
        $topicName = (string)($task['topicName'] ?? '');
        $awardName = (string)($task['awardName'] ?? '');

        $targetUids = $this->extractSpaceUids($jumpLink);
        if ($targetUids === [] && str_contains($taskName, '关注')) {
            $targetUids = $this->matchFollowTargetUids($taskName, $pageFollowTargets);
        }

        if ($topicId === '' && str_contains($jumpLink, 'topic_id=')) {
            $topicId = $this->extractTopicIdFromLink($jumpLink);
        }

        if ($topicId === '' && count($pageTopicIds) === 1) {
            $topicId = $pageTopicIds[0];
        }

        $targetVideoIds = [];
        $targetArchives = [];
        if (str_contains($taskName, '观看') && !str_contains($taskName, '直播')) {
            $targetVideoIds = $pageVideoIds;
            $targetArchives = $pageVideoArchives;
        }
        if ($topicId !== '' && $pageVideoArchives !== [] && (str_contains($taskName, '点赞') || str_contains($taskName, '投币') || str_contains($taskName, '评论'))) {
            $targetArchives = $pageVideoArchives;
            $targetVideoIds = $pageVideoIds;
        }

        $targetRoomIds = [];
        $targetAreaId = 0;
        $targetParentAreaId = 0;
        if (str_contains($taskName, '直播')) {
            $targetRoomIds = $pageLiveRoomIds;
            ['area_id' => $targetAreaId, 'parent_area_id' => $targetParentAreaId] = $this->extractLiveAreaTarget($jumpLink);
        }

        [$capability, $supportLevel] = $this->classifyTask(
            $taskName,
            $btnBehavior,
            $topicId,
            $targetUids,
            $targetVideoIds,
            $targetRoomIds,
            $targetAreaId,
        );

        return new EraActivityTask(
            (string)($task['taskId'] ?? ''),
            $taskName,
            $capability,
            $supportLevel,
            $btnBehavior,
            (string)($task['counter'] ?? ''),
            $jumpLink,
            (string)($task['jumpPosition'] ?? ''),
            $topicId,
            $topicName,
            $awardName,
            $this->extractRequiredWatchSeconds($taskName),
            (int)($task['taskStatus'] ?? 0),
            (int)($task['taskType'] ?? 0),
            (int)($task['periodType'] ?? 0),
            (int)($task['taskAwardType'] ?? 0),
            $targetUids,
            $targetVideoIds,
            $targetRoomIds,
            $targetAreaId,
            $targetParentAreaId,
            $targetArchives,
            $this->normalizeCheckpointList($task['checkpoints'] ?? []),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectVideoArchives(array $state): array
    {
        $archives = [];

        foreach (($state['H5SlideVideos'] ?? []) as $item) {
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

        foreach (($state['PcVideoCard'] ?? []) as $item) {
            foreach (($item['videoConfig']['videoList'] ?? []) as $video) {
                if (!is_array($video)) {
                    continue;
                }

                $videoId = trim((string)($video['videoID'] ?? ''));
                if ($videoId === '') {
                    continue;
                }

                $archives[] = [
                    'aid' => ctype_digit($videoId) ? $videoId : '',
                    'bvid' => str_starts_with(strtoupper($videoId), 'BV') ? $videoId : '',
                    'title' => trim((string)($video['title'] ?? $video['textContent'] ?? '')),
                ];
            }
        }

        foreach (($state['YunqueVideoCard'] ?? []) as $item) {
            $bvid = trim((string)($item['bvid'] ?? ''));
            if ($bvid === '') {
                continue;
            }

            $archives[] = [
                'aid' => '',
                'bvid' => $bvid,
                'title' => trim((string)($item['title'] ?? '')),
            ];
        }

        return $this->deduplicateArchives($archives);
    }

    /**
     * 处理extractRequired观看Seconds
     * @param string $taskName
     * @return int
     */
    private function extractRequiredWatchSeconds(string $taskName): int
    {
        if (preg_match('/(\d+)\s*分钟/u', $taskName, $matches) === 1) {
            return max(0, (int)$matches[1]) * 60;
        }

        if (preg_match('/(\d+)\s*秒/u', $taskName, $matches) === 1) {
            return max(0, (int)$matches[1]);
        }

        return 0;
    }

    /**
     * @param string[] $btnBehavior
     * @param string[] $targetUids
     * @param string[] $targetVideoIds
     * @param string[] $targetRoomIds
     * @return array{0: string, 1: string}
     */
    private function classifyTask(
        string $taskName,
        array $btnBehavior,
        string $topicId,
        array $targetUids,
        array $targetVideoIds,
        array $targetRoomIds,
        int $targetAreaId,
    ): array {
        if (str_contains($taskName, '投稿') || str_contains($taskName, '开播') || str_contains($taskName, '下载')) {
            return [EraActivityTask::CAPABILITY_MANUAL, EraActivityTask::SUPPORT_MANUAL];
        }

        if (str_contains($taskName, '签到') || str_contains($taskName, '讨论') || str_contains($taskName, '榜单')) {
            return [EraActivityTask::CAPABILITY_MANUAL, EraActivityTask::SUPPORT_MANUAL];
        }

        if (str_contains($taskName, '分享')) {
            return [EraActivityTask::CAPABILITY_SHARE, EraActivityTask::SUPPORT_NOW];
        }

        if (str_contains($taskName, '关注')) {
            return [EraActivityTask::CAPABILITY_FOLLOW, $targetUids !== [] ? EraActivityTask::SUPPORT_NOW : EraActivityTask::SUPPORT_LATER];
        }

        if (str_contains($taskName, '直播') && str_contains($taskName, '观看')) {
            return [EraActivityTask::CAPABILITY_WATCH_LIVE, ($targetRoomIds !== [] || $targetAreaId > 0) ? EraActivityTask::SUPPORT_NOW : EraActivityTask::SUPPORT_LATER];
        }

        if (str_contains($taskName, '观看')) {
            if ($targetVideoIds !== []) {
                return [EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED, EraActivityTask::SUPPORT_NOW];
            }

            if ($topicId !== '') {
                return [EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC, EraActivityTask::SUPPORT_NOW];
            }

            return [EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC, EraActivityTask::SUPPORT_LATER];
        }

        if (str_contains($taskName, '投币') || str_contains($taskName, '点赞') || str_contains($taskName, '评论')) {
            return [EraActivityTask::CAPABILITY_MANUAL, EraActivityTask::SUPPORT_MANUAL];
        }

        if (in_array('SCROLL', $btnBehavior, true)) {
            return [EraActivityTask::CAPABILITY_MANUAL, EraActivityTask::SUPPORT_MANUAL];
        }

        return [EraActivityTask::CAPABILITY_UNKNOWN, EraActivityTask::SUPPORT_LATER];
    }

    /**
     * @param array<int, array<int, string>> $candidates
     */
    private function collectFirstNonEmpty(array $state, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = $this->extractNestedValue($state, $candidate);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param string[] $segments
     */
    private function extractNestedValue(mixed $value, array $segments, int $offset = 0): mixed
    {
        if ($offset >= count($segments)) {
            return $value;
        }

        if (!is_array($value)) {
            return null;
        }

        $segment = $segments[$offset];
        if (array_key_exists($segment, $value)) {
            return $this->extractNestedValue($value[$segment], $segments, $offset + 1);
        }

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $nested = $this->extractNestedValue($item, $segments, $offset);
            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function extractSpaceUids(string $jumpLink): array
    {
        if (preg_match_all('~space\.bilibili\.com/(\d+)~', $jumpLink, $matches) !== 1 && preg_match_all('~space\.bilibili\.com/(\d+)~', $jumpLink, $matches) === 0) {
            return [];
        }

        return $this->normalizeStringArray($matches[1] ?? []);
    }

    /**
     * 处理extract话题IdFromLink
     * @param string $jumpLink
     * @return string
     */
    private function extractTopicIdFromLink(string $jumpLink): string
    {
        if (preg_match('~topic_id=(\d+)~', $jumpLink, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @return array{area_id: int, parent_area_id: int}
     */
    private function extractLiveAreaTarget(string $jumpLink): array
    {
        $jumpLink = html_entity_decode($jumpLink, ENT_QUOTES | ENT_HTML5);
        $query = parse_url($jumpLink, PHP_URL_QUERY);
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
     * @param EraActivityFollowTarget[] $targets
     * @return string[]
     */
    private function matchFollowTargetUids(string $taskName, array $targets): array
    {
        $matched = [];
        foreach ($targets as $target) {
            $name = trim($target->uname);
            if ($name !== '' && str_contains($taskName, $name)) {
                $matched[] = $target->uid;
            }
        }

        if ($matched !== []) {
            return array_values(array_unique($matched));
        }

        $lotteryTargets = array_values(array_filter(
            $targets,
            static fn(EraActivityFollowTarget $target): bool => $target->addLotteryTimes
        ));
        if (count($lotteryTargets) === 1) {
            return [$lotteryTargets[0]->uid];
        }

        if (count($targets) === 1) {
            return [$targets[0]->uid];
        }

        return [];
    }

    /**
     * @param EraActivityFollowTarget[] $targets
     * @return EraActivityFollowTarget[]
     */
    private function deduplicateFollowTargets(array $targets): array
    {
        $unique = [];
        foreach ($targets as $target) {
            if ($target->uid === '') {
                continue;
            }

            if (!isset($unique[$target->uid])) {
                $unique[$target->uid] = $target;
                continue;
            }

            if ($target->addLotteryTimes && !$unique[$target->uid]->addLotteryTimes) {
                $unique[$target->uid] = $target;
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
            $key = $aid !== '' ? "aid:{$aid}" : ($bvid !== '' ? "bvid:{$bvid}" : '');
            if ($key === '') {
                continue;
            }

            $unique[$key] = $archive;
        }

        return array_values($unique);
    }

    /**
     * @param mixed $value
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
     * @param mixed $value
     * @return string[]
     */
    private function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_string($item) || is_int($item)) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $normalized[] = $item;
                }
            }
        }

        return array_values(array_unique($normalized));
    }
}
