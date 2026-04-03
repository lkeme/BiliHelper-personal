<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Node;

use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\ActivityLottery\Internal\Gateway\WatchVideoGateway;
use Bhp\Plugin\ActivityLottery\Internal\Page\EraTaskSnapshot;
use RuntimeException;

final class EraWatchVideoNodeRunner implements NodeRunnerInterface
{
    public function __construct(
        private readonly string $nodeType,
        private readonly WatchVideoGateway $watchGateway = new WatchVideoGateway(),
    ) {
        if (!in_array($this->nodeType, ['era_task_watch_video_fixed', 'era_task_watch_video_topic'], true)) {
            throw new RuntimeException('不支持的视频节点类型: ' . $this->nodeType);
        }
    }

    public function type(): string
    {
        return $this->nodeType;
    }

    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if ($taskView->taskId() === '' || $task === null) {
            return new ActivityNodeResult(true, '视频任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $state = $taskView->taskRuntime();
        $resolvedArchive = $this->resolveArchive($task, $state);
        if ($resolvedArchive === null) {
            return new ActivityNodeResult(true, '无法解析视频稿件信息，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $archive = $resolvedArchive['archive'];
        $state = array_replace($state, $resolvedArchive['state']);
        $sessionId = trim((string)($state['watch_video_session'] ?? ''));
        $startedAt = (int)($state['watch_video_started_at'] ?? 0);

        if ($sessionId === '' || $startedAt <= 0) {
            $sessionId = self::generateSessionId();
            if (!$this->watchGateway->start($archive, $sessionId)) {
                return new ActivityNodeResult(false, '视频观看初始化失败', [
                    'node_status' => ActivityNodeStatus::FAILED,
                ], $now);
            }

            $localWatchSeconds = max(0, (int)($state['local_watch_seconds'] ?? 0));
            $waitSeconds = EraWatchProgress::resolveWaitSeconds(
                $task,
                max(1, (int)($archive['duration'] ?? 0)),
                null,
                $localWatchSeconds,
            );
            $nextState = array_replace($state, [
                'watch_video_archive' => $archive,
                'watch_video_session' => $sessionId,
                'watch_video_started_at' => $now,
                'watch_video_wait_seconds' => $waitSeconds,
            ]);

            return new ActivityNodeResult(true, '视频观看已启动', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + $waitSeconds,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        $watchedSeconds = max(1, $now - $startedAt);
        if (!$this->watchGateway->finish($archive, $watchedSeconds, $sessionId)) {
            return new ActivityNodeResult(false, '视频观看收尾失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $localWatchSeconds = max(0, (int)($state['local_watch_seconds'] ?? 0)) + $watchedSeconds;
        $nextState = $state;
        $nextState['local_watch_seconds'] = $localWatchSeconds;
        unset(
            $nextState['watch_video_archive'],
            $nextState['watch_video_session'],
            $nextState['watch_video_started_at'],
            $nextState['watch_video_wait_seconds'],
        );

        if (EraWatchProgress::targetSeconds($task, null, $localWatchSeconds) > 0) {
            $nextState = $this->advanceArchiveCursor($task, $nextState);

            return new ActivityNodeResult(true, '视频观看继续推进', [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + 5,
                'context_patch' => $taskView->replaceTaskRuntime($nextState),
            ], $now);
        }

        return new ActivityNodeResult(true, '视频观看任务完成', [
            'node_status' => ActivityNodeStatus::SUCCEEDED,
            'context_patch' => $taskView->replaceTaskRuntime($nextState),
        ], $now);
    }

    /**
     * @param array<string, mixed> $state
     * @return array{archive: array<string, mixed>, state: array<string, mixed>}|null
     */
    private function resolveArchive(EraTaskSnapshot $task, array $state): ?array
    {
        $storedArchive = $this->watchGateway->normalizeArchive(
            is_array($state['watch_video_archive'] ?? null) ? $state['watch_video_archive'] : [],
        );
        if ($storedArchive !== null) {
            return ['archive' => $storedArchive, 'state' => $state];
        }

        return $this->nodeType === 'era_task_watch_video_fixed'
            ? $this->resolveFixedArchive($task, $state)
            : $this->resolveTopicArchive($task, $state);
    }

    /**
     * @param array<string, mixed> $state
     * @return array{archive: array<string, mixed>, state: array<string, mixed>}|null
     */
    private function resolveFixedArchive(EraTaskSnapshot $task, array $state): ?array
    {
        $archives = $this->fixedArchives($task);
        if ($archives === []) {
            return null;
        }

        $index = max(0, (int)($state['fixed_archive_index'] ?? 0));
        if (!isset($archives[$index])) {
            $index = 0;
        }

        $archive = $this->watchGateway->normalizeArchive($archives[$index]);
        if ($archive === null) {
            return null;
        }

        $state['fixed_archive_index'] = $index;
        return ['archive' => $archive, 'state' => $state];
    }

    /**
     * @param array<string, mixed> $state
     * @return array{archive: array<string, mixed>, state: array<string, mixed>}|null
     */
    private function resolveTopicArchive(EraTaskSnapshot $task, array $state): ?array
    {
        $archives = is_array($state['topic_archives'] ?? null)
            ? $this->normalizeArchives($state['topic_archives'])
            : [];
        if ($archives === []) {
            $archives = array_merge(
                $this->normalizeArchives($task->targetArchives()),
                $task->topicId() !== '' ? $this->watchGateway->fetchTopicArchives($task->topicId()) : [],
            );
            $archives = $this->normalizeArchives($archives);
            $state['topic_archives'] = $archives;
            $state['topic_archive_index'] = 0;
        }

        $index = max(0, (int)($state['topic_archive_index'] ?? 0));
        if (!isset($archives[$index])) {
            return null;
        }

        $archive = $this->watchGateway->normalizeArchive($archives[$index]);
        if ($archive === null) {
            return null;
        }

        $state['topic_archive_index'] = $index;
        return ['archive' => $archive, 'state' => $state];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fixedArchives(EraTaskSnapshot $task): array
    {
        $archives = $this->normalizeArchives($task->targetArchives());
        if ($archives !== []) {
            return $archives;
        }

        $normalized = [];
        foreach ($task->targetVideoIds() as $videoId) {
            $label = trim($videoId);
            if ($label === '') {
                continue;
            }

            $normalized[] = [
                'aid' => ctype_digit($label) ? $label : '',
                'bvid' => str_starts_with(strtoupper($label), 'BV') ? $label : '',
            ];
        }

        return $this->normalizeArchives($normalized);
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function advanceArchiveCursor(EraTaskSnapshot $task, array $state): array
    {
        if ($this->nodeType === 'era_task_watch_video_fixed') {
            $archives = $this->fixedArchives($task);
            $index = max(0, (int)($state['fixed_archive_index'] ?? 0));
            if (isset($archives[$index + 1])) {
                $state['fixed_archive_index'] = $index + 1;
            }

            return $state;
        }

        $archives = is_array($state['topic_archives'] ?? null) ? $state['topic_archives'] : [];
        $index = max(0, (int)($state['topic_archive_index'] ?? 0));
        if (isset($archives[$index + 1])) {
            $state['topic_archive_index'] = $index + 1;
        }

        return $state;
    }

    /**
     * @param array<int, array<string, mixed>> $archives
     * @return array<int, array<string, mixed>>
     */
    private function normalizeArchives(array $archives): array
    {
        $normalized = [];
        $seen = [];

        foreach ($archives as $archive) {
            if (!is_array($archive)) {
                continue;
            }

            $aid = trim((string)($archive['aid'] ?? ''));
            $bvid = trim((string)($archive['bvid'] ?? ''));
            $key = $aid !== '' ? 'aid:' . $aid : ($bvid !== '' ? 'bvid:' . $bvid : '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $archive;
        }

        return $normalized;
    }

    private static function generateSessionId(): string
    {
        try {
            return strtolower(bin2hex(random_bytes(16)));
        } catch (\Throwable) {
            return strtolower(md5(uniqid((string)mt_rand(), true)));
        }
    }
}
