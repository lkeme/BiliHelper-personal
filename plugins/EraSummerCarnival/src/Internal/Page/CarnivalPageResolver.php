<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Page;

use Bhp\Activity\Era\EraPagePayloadExtractor;
use Bhp\Plugin\Builtin\EraSummerCarnival\Internal\Support\CarnivalIds;
use Throwable;

/**
 * 活动页 → CarnivalSnapshot。
 *
 * 解析复用 Bhp\Activity\Era\EraPagePayloadExtractor：
 * 它已处理 window.__initialState 与 window.__BILIACT_EVAPAGEDATA__，
 * 并按组件 name 聚合 props，产出形如 ['EraTasklistPc' => [props, ...], ...]。
 *
 * 任一关键字段缺失时回落 CarnivalIds 常量，并把字段名记入 fallbackFields 以便告警。
 */
final class CarnivalPageResolver
{
    private readonly EraPagePayloadExtractor $extractor;

    /** @var \Closure(string): string */
    private readonly \Closure $htmlFetcher;

    /** @var \Closure(string, string, array<string, mixed>): void */
    private readonly \Closure $logger;

    /**
     * @param callable(string): string $htmlFetcher
     * @param callable(string, string, array<string, mixed>): void $logger
     */
    public function __construct(
        callable $htmlFetcher,
        callable $logger,
        ?EraPagePayloadExtractor $extractor = null,
    ) {
        $this->htmlFetcher = \Closure::fromCallable($htmlFetcher);
        $this->logger = \Closure::fromCallable($logger);
        $this->extractor = $extractor ?? new EraPagePayloadExtractor();
    }

    /**
     * 解析活动页快照
     * @param int $now
     * @return CarnivalSnapshot
     */
    public function resolve(int $now): CarnivalSnapshot
    {
        $state = [];
        $pageInfo = [];

        try {
            $html = ($this->htmlFetcher)(CarnivalIds::ACTIVITY_URL);
            if (trim($html) !== '') {
                $state = $this->extractor->extractState($html) ?? [];
                $pageInfo = $this->extractor->extractPageInfo($html);
            }
        } catch (Throwable $throwable) {
            $this->log('warning', '次元奇旅: 活动页抓取失败，本轮使用内置配置兜底', [
                'error' => $throwable->getMessage(),
            ]);
        }

        $fallback = [];
        $signIn = $this->resolveSignIn($state);
        $tasks = $this->resolveTasklistTasks($state);
        $lottery = $this->resolveLottery($state);

        $activityId = $this->pick($lottery['activity_id'] ?? '', CarnivalIds::ACTIVITY_ID, 'activity_id', $fallback);
        $lotteryId = $this->pick($lottery['lottery_id'] ?? '', CarnivalIds::LOTTERY_ID, 'lottery_id', $fallback);
        $activityName = $this->pick(
            trim((string)($pageInfo['title'] ?? '')),
            CarnivalIds::ACTIVITY_NAME,
            'activity_name',
            $fallback,
        );
        $liveSourceId = $this->pick($this->resolveLiveSourceId($state), CarnivalIds::LIVE_SOURCE_ID, 'live_source_id', $fallback);
        $signInTaskId = $this->pick($signIn['task_id'] ?? '', CarnivalIds::SIGN_IN_TASK_ID, 'sign_in_task_id', $fallback);
        $signInCounter = $this->pick($signIn['counter'] ?? '', CarnivalIds::SIGN_IN_COUNTER, 'sign_in_counter', $fallback);
        $signInTaskName = $this->pick($signIn['task_name'] ?? '', CarnivalIds::SIGN_IN_TASK_NAME, 'sign_in_task_name', $fallback);
        $watchLiveTaskId = $this->pick($tasks['watch_live']['task_id'] ?? '', CarnivalIds::WATCH_LIVE_TASK_ID, 'watch_live_task_id', $fallback);
        $watchLiveCounter = $this->pick($tasks['watch_live']['counter'] ?? '', CarnivalIds::WATCH_LIVE_COUNTER, 'watch_live_counter', $fallback);

        $checkpoints = $signIn['checkpoints'] ?? [];
        if ($checkpoints === []) {
            $checkpoints = CarnivalIds::signInCheckpoints();
            $fallback[] = 'sign_in_checkpoints';
        }

        $followTasks = $tasks['follow'] ?? [];
        if ($followTasks === []) {
            $followTasks = CarnivalIds::followTasks();
            $fallback[] = 'follow_tasks';
        }

        $followTargets = $this->resolveFollowTargets($state);
        if ($followTargets === []) {
            $this->log('warning', '次元奇旅: 活动页未解析到关注目标，关注任务本轮跳过', []);
        }

        // page_id 不出现在组件 props 中（仅活动页自身 JS 请求参数携带），固定用内置值
        $pageId = CarnivalIds::PAGE_ID;

        $snapshot = new CarnivalSnapshot(
            $activityId,
            $activityName,
            $pageId,
            $lotteryId,
            CarnivalIds::ACTIVITY_URL,
            $liveSourceId,
            $signInTaskId,
            $signInCounter,
            $signInTaskName,
            $checkpoints,
            $watchLiveTaskId,
            $watchLiveCounter,
            max(1, (int)($tasks['watch_live']['target_seconds'] ?? CarnivalIds::WATCH_LIVE_TARGET_SECONDS)),
            $followTasks,
            $followTargets,
            array_values(array_unique($fallback)),
            $now,
        );

        if ($snapshot->fallbackFields !== []) {
            $this->log('warning', '次元奇旅: 部分活动配置解析失败，已回落内置值', [
                'fields' => implode(',', $snapshot->fallbackFields),
            ]);
        }

        return $snapshot;
    }

    /**
     * 解析签到任务（EvaTaskButton.taskItem）
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function resolveSignIn(array $state): array
    {
        $candidates = [];
        foreach ($this->componentPropsList($state, 'EvaTaskButton') as $props) {
            $taskItem = $props['taskItem'] ?? null;
            if (!is_array($taskItem)) {
                continue;
            }

            $taskId = trim((string)($taskItem['taskId'] ?? $taskItem['task_id'] ?? ''));
            if ($taskId === '') {
                continue;
            }

            $behaviors = array_map(
                static fn (mixed $item): string => strtoupper(trim((string)$item)),
                is_array($taskItem['btnBehavior'] ?? null) ? $taskItem['btnBehavior'] : [],
            );
            $isSignInAction = in_array('FINISH-FINISH', $behaviors, true);
            $taskName = trim((string)($taskItem['taskName'] ?? $taskItem['task_name'] ?? ''));

            $entry = [
                'task_id' => $taskId,
                'counter' => trim((string)($taskItem['counter'] ?? '')),
                'task_name' => $taskName,
                'checkpoints' => $this->normalizeCheckpoints($taskItem['checkpoints'] ?? []),
            ];

            // 优先取带 FINISH-FINISH 行为的签到按钮，其次按任务名兜底
            if ($isSignInAction) {
                return $entry;
            }
            if (str_contains($taskName, '签到')) {
                $candidates[] = $entry;
            }
        }

        return $candidates[0] ?? [];
    }

    /**
     * 解析任务列表（EraTasklistPc.tasklist）
     *
     * 注意：配置快照里的 taskName 是活动上线时的名称，运营方可能在服务端改名
     * （实测同一 taskId 配置叫「关注优质VLOG稿件UP主」、实时接口已改叫「关注暑期追更UP主」）。
     * 分类依赖名称关键字，因此对无法归类的任务显式告警，避免改名后静默漏做。
     *
     * 同理，快照里的 indicators / status 是陈旧值（实测配置显示 15/15、实时接口只有 2/15），
     * 一律不作为进度判据 —— 进度只认 totalv2。
     *
     * @param array<string, mixed> $state
     * @return array{watch_live?: array<string, mixed>, follow?: array<string, array{counter: string, task_name: string}>}
     */
    private function resolveTasklistTasks(array $state): array
    {
        $result = ['follow' => []];
        $unclassified = [];

        foreach ($this->componentPropsList($state, 'EraTasklistPc') as $props) {
            $taskList = $props['tasklist'] ?? null;
            if (!is_array($taskList)) {
                continue;
            }

            foreach ($taskList as $task) {
                if (!is_array($task)) {
                    continue;
                }

                $taskId = trim((string)($task['taskId'] ?? $task['task_id'] ?? ''));
                $counter = trim((string)($task['counter'] ?? ''));
                $taskName = trim((string)($task['taskName'] ?? $task['task_name'] ?? ''));
                if ($taskId === '' || $counter === '') {
                    continue;
                }

                // 弹幕任务本插件不实现，显式跳过而非归入未分类
                if (str_contains($taskName, '弹幕')) {
                    continue;
                }

                if (str_contains($taskName, '直播') && str_contains($taskName, '观看')) {
                    $result['watch_live'] = [
                        'task_id' => $taskId,
                        'counter' => $counter,
                        'task_name' => $taskName,
                        'target_seconds' => CarnivalIds::WATCH_LIVE_TARGET_SECONDS,
                    ];
                    continue;
                }

                if (str_contains($taskName, '关注')) {
                    $result['follow'][$taskId] = [
                        'counter' => $counter,
                        'task_name' => $taskName,
                    ];
                    continue;
                }

                $unclassified[] = "{$taskId}({$taskName})";
            }
        }

        if ($unclassified !== []) {
            $this->log('warning', '次元奇旅: 存在无法归类的活动任务，本插件不会执行它们', [
                'tasks' => implode(', ', $unclassified),
            ]);
        }

        return $result;
    }

    /**
     * 解析抽奖配置（EraLotteryPc.config）
     *
     * @param array<string, mixed> $state
     * @return array<string, string>
     */
    private function resolveLottery(array $state): array
    {
        foreach ($this->componentPropsList($state, 'EraLotteryPc') as $props) {
            $config = $props['config'] ?? null;
            if (!is_array($config)) {
                continue;
            }

            $lotteryId = trim((string)($config['lottery_id'] ?? ''));
            if ($lotteryId === '') {
                continue;
            }

            return [
                'activity_id' => trim((string)($config['activity_id'] ?? '')),
                'lottery_id' => $lotteryId,
            ];
        }

        return [];
    }

    /**
     * 解析板块直播运营位（LiveroomList.sourceId）
     *
     * @param array<string, mixed> $state
     */
    private function resolveLiveSourceId(array $state): string
    {
        foreach ($this->componentPropsList($state, 'LiveroomList') as $props) {
            $sourceId = trim((string)($props['sourceId'] ?? $props['source_id'] ?? ''));
            if ($sourceId !== '') {
                return $sourceId;
            }
        }

        return '';
    }

    /**
     * 解析关注目标 mid 池
     *
     * @param array<string, mixed> $state
     * @return int[]
     */
    private function resolveFollowTargets(array $state): array
    {
        $targets = [];

        foreach ($this->componentPropsList($state, 'EvaAnchorCarousel') as $props) {
            $list = $props['anchorConfigList'] ?? null;
            if (!is_array($list)) {
                continue;
            }

            foreach ($list as $anchor) {
                if (!is_array($anchor)) {
                    continue;
                }

                $mid = (int)($anchor['uid'] ?? 0);
                if ($mid > 0) {
                    $targets[] = $mid;
                }
            }
        }

        foreach ($this->componentPropsList($state, 'EvaFollowButton') as $props) {
            $mid = (int)($props['uid'] ?? 0);
            if ($mid > 0) {
                $targets[] = $mid;
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * 标准化 checkpoint，兼容 ztasksid/sid 与 awardname/award_name 两种键名
     *
     * @return array<string, array{days: int, reward_name: string}>
     */
    private function normalizeCheckpoints(mixed $checkpoints): array
    {
        if (!is_array($checkpoints)) {
            return [];
        }

        $normalized = [];
        foreach ($checkpoints as $checkpoint) {
            if (!is_array($checkpoint)) {
                continue;
            }

            $sid = trim((string)($checkpoint['ztasksid'] ?? $checkpoint['sid'] ?? ''));
            if ($sid === '') {
                continue;
            }

            $days = 0;
            $list = $checkpoint['list'] ?? null;
            if (is_array($list) && is_array($list[0] ?? null)) {
                $days = max(0, (int)($list[0]['limit'] ?? 0));
            }

            $normalized[$sid] = [
                'days' => $days,
                'reward_name' => trim((string)($checkpoint['awardname'] ?? $checkpoint['award_name'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * 取组件 props 列表，兼容 extractor 合并后可能出现的非 list 结构
     *
     * @param array<string, mixed> $state
     * @return array<int, array<string, mixed>>
     */
    private function componentPropsList(array $state, string $componentName): array
    {
        $value = $state[$componentName] ?? null;
        if (!is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            return [$value];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * 取解析值，为空则回落内置值并登记字段名
     *
     * @param string[] $fallbackFields
     */
    private function pick(mixed $resolved, string $builtin, string $field, array &$fallbackFields): string
    {
        $value = trim((string)$resolved);
        if ($value !== '') {
            return $value;
        }

        $fallbackFields[] = $field;

        return $builtin;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        ($this->logger)($level, $message, $context);
    }
}
