<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Node;

use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityFlow;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNode;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeResult;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Flow\ActivityNodeStatus;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Gateway\WatchVideoGateway;
use Bhp\Plugin\Builtin\ActivityLottery\Internal\Page\EraTaskSnapshot;
use Bhp\Util\Exceptions\RequestException;
use RuntimeException;

final class EraWatchVideoNodeRunner implements NodeRunnerInterface
{
    private const RETRY_DELAY_SECONDS = 300;

    /**
     * 初始化 EraWatchVideoNodeRunner
     * @param string $nodeType
     * @param WatchVideoGateway $watchGateway
     */
    public function __construct(
        private readonly string $nodeType,
        private readonly ?WatchVideoGateway $watchGateway = null,
    ) {
        if (!in_array($this->nodeType, ['era_task_watch_video_fixed', 'era_task_watch_video_topic'], true)) {
            throw new RuntimeException('不支持的视频节点类型: ' . $this->nodeType);
        }
    }

    /**
     * 获取类型标识
     * @return string
     */
    public function type(): string
    {
        return $this->nodeType;
    }

    /**
     * 启动执行流程
     * @param ActivityFlow $flow
     * @param ActivityNode $node
     * @param int $now
     * @return ActivityNodeResult
     */
    public function run(ActivityFlow $flow, ActivityNode $node, int $now): ActivityNodeResult
    {
        $taskView = ResolvedEraTaskView::fromFlowAndNode($flow, $node);
        $task = $taskView->task();
        if (!$this->watchGateway instanceof WatchVideoGateway) {
            throw new RuntimeException('视频观看网关未配置');
        }
        if ($taskView->taskId() === '' || $task === null) {
            return new ActivityNodeResult(true, '视频任务缺少 task_id 或快照，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $state = $taskView->taskRuntime();
        $progress = $taskView->taskProgress();
        try {
            $resolvedArchive = $this->resolveArchive($task, $state);
        } catch (RequestException $exception) {
            return new ActivityNodeResult(false, '视频稿件解析失败: ' . $exception->getMessage(), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }

        if ($resolvedArchive === null) {
            return new ActivityNodeResult(true, '无法解析视频稿件信息，已跳过', [
                'node_status' => ActivityNodeStatus::SKIPPED,
            ], $now);
        }

        $archive = $resolvedArchive['archive'];
        $state = array_replace($state, $resolvedArchive['state']);
        $sessionId = trim((string)($state['watch_video_session'] ?? ''));
        $startedAt = (int)($state['watch_video_started_at'] ?? 0);
        $serverWatchSeconds = EraWatchProgress::currentSeconds($task, $progress);
        $localWatchSeconds = max(max(0, (int)($state['local_watch_seconds'] ?? 0)), $serverWatchSeconds);
        if ($localWatchSeconds > 0) {
            $state['local_watch_seconds'] = $localWatchSeconds;
        }

        if ($taskView->resolvedTaskStatus() === 3) {
            unset(
                $state['watch_video_archive'],
                $state['watch_video_session'],
                $state['watch_video_started_at'],
                $state['watch_video_wait_seconds'],
            );

            return new ActivityNodeResult(true, '视频观看任务完成', [
                'node_status' => ActivityNodeStatus::SUCCEEDED,
                'context_patch' => $taskView->replaceTaskRuntime($state),
            ], $now);
        }

        if ($sessionId === '' || $startedAt <= 0) {
            $sessionId = self::generateSessionId();
            try {
                $started = $this->watchGateway->start($archive, $sessionId);
            } catch (RequestException $exception) {
                return new ActivityNodeResult(false, '视频观看初始化失败: ' . $exception->getMessage(), [
                    'node_status' => ActivityNodeStatus::WAITING,
                    'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
                ], $now);
            }

            if (!$started) {
                return new ActivityNodeResult(false, '视频观看初始化失败', [
                    'node_status' => ActivityNodeStatus::FAILED,
                ], $now);
            }

            $waitSeconds = EraWatchProgress::resolveWaitSeconds(
                $task,
                max(1, (int)($archive['duration'] ?? 0)),
                $progress,
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
        try {
            $finished = $this->watchGateway->finish($archive, $watchedSeconds, $sessionId);
        } catch (RequestException $exception) {
            return new ActivityNodeResult(false, '视频观看收尾失败: ' . $exception->getMessage(), [
                'node_status' => ActivityNodeStatus::WAITING,
                'next_run_at' => $now + self::RETRY_DELAY_SECONDS,
            ], $now);
        }

        if (!$finished) {
            return new ActivityNodeResult(false, '视频观看收尾失败', [
                'node_status' => ActivityNodeStatus::FAILED,
            ], $now);
        }

        $localWatchSeconds = max(max(0, (int)($state['local_watch_seconds'] ?? 0)), $serverWatchSeconds) + $watchedSeconds;
        $nextState = $state;
        $nextState['local_watch_seconds'] = $localWatchSeconds;
        unset(
            $nextState['watch_video_archive'],
            $nextState['watch_video_session'],
            $nextState['watch_video_started_at'],
            $nextState['watch_video_wait_seconds'],
        );

        if (EraWatchProgress::targetSeconds($task, $progress, $localWatchSeconds) > 0) {
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
                $this->archivesFromVideoIds($task),
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

        return $this->normalizeArchives($this->archivesFromVideoIds($task));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function archivesFromVideoIds(EraTaskSnapshot $task): array
    {
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

        return $normalized;
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

    /**
     * 处理generate会话Id
     * @return string
     */
    private static function generateSessionId(): string
    {
        try {
            return strtolower(bin2hex(random_bytes(16)));
        } catch (\Throwable) {
            return strtolower(md5(uniqid((string)mt_rand(), true)));
        }
    }
}

