<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

require_once __DIR__ . '/Internal/bootstrap.php';
require_once __DIR__ . '/Concerns/ActivityLotteryCampaignDrawFlow.php';

use Bhp\Api\Api\X\Activity\ApiActivity;
use Bhp\Api\Api\X\ActivityComponents\ApiMission;
use Bhp\Api\Api\X\Relation\ApiRelation;
use Bhp\Api\Api\X\Task\ApiTask;
use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Plugin\ActivityLottery\Internal\ActivityCampaign;
use Bhp\Plugin\ActivityLottery\Internal\EraActivityPage;
use Bhp\Plugin\ActivityLottery\Internal\EraActivityPageParser;
use Bhp\Plugin\ActivityLottery\Internal\EraActivityTask;
use Bhp\Plugin\ActivityLottery\Internal\EraLiveWatchService;
use Bhp\Plugin\ActivityLottery\Internal\EraTopicArchiveService;
use Bhp\Plugin\ActivityLottery\Internal\EraVideoWatchService;
use Bhp\Request\Request;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\GhProxy\GhProxy;

class ActivityLottery extends BasePlugin implements PluginTaskInterface
{
    use ActivityLotteryCampaignDrawFlow;

    protected const DRAW_BACKEND_SID_LOTTERY = 'sid_lottery';
    protected const ERA_TASK_MAX_ATTEMPTS = 3;
    protected const ERA_POOL_CAPACITY = 4;
    protected const ERA_NON_LIVE_BATCH_LIMIT = 4;
    protected const ERA_TOPIC_STEP_DELAY_SECONDS = 5;
    protected const ERA_FOLLOW_STEP_DELAY_SECONDS = 15;
    protected const ERA_UNFOLLOW_STEP_DELAY_SECONDS = 15;
    protected const CAMPAIGN_DRAW_CLAIM_BATCH_LIMIT = 4;
    protected const CAMPAIGN_DRAW_REFRESH_BATCH_LIMIT = 4;
    protected const CAMPAIGN_DRAW_EXECUTE_BATCH_LIMIT = 8;

    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'ActivityLottery', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '转盘活动', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1117, // 插件优先级
        'cycle' => '3-7(分钟)', //  运行周期
        'start' => '07:30:00', // 插件运行开始时间
        'end' => '23:30:00', // 插件运行结束时间
    ];

    /**
     * @var array
     */
    protected array $config = [
        'campaign_draw_disabled_ids' => [],
        'campaign_draw_credit_claim_queue' => [],
        'campaign_draw_credit_refresh_queue' => [],
        'campaign_draw_execute_queue' => [],
        'wait_era_tasks' => [],
        'pending_era_tasks' => [],
        'done_era_task_keys' => [],
        'era_task_states' => [],
        'era_pool_state' => [],
    ];

    /**
     * @var array
     */
    protected array $campaignDrawZeroCreditSeen = [];

    protected ?EraLiveWatchService $eraLiveWatchService = null;
    protected ?EraTopicArchiveService $eraTopicArchiveService = null;
    protected ?EraVideoWatchService $eraVideoWatchService = null;

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        Cache::initCache();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('activity_lottery')) {
            return TaskResult::keepSchedule();
        }

        $this->resetTaskResult();
        $this->initConfig();
        if ($this->isFinishedToday()) {
            return TaskResult::nextDayAt(7, 30);
        }

        $this->runCampaignTaskStage();
        $this->runCampaignDrawStage();
        $this->initConfig(true);

        if ($this->isFinishedToday()) {
            return $this->resolveTaskResult(TaskResult::nextDayAt(7, 30));
        }

        return $this->resolveTaskResult(TaskResult::after(mt_rand(3, 7) * 60));
    }

    protected function runCampaignTaskStage(): void
    {
        $this->fetchRemoteInfos();
        $this->fillEraTaskPool();
        $this->runDueEraLiveTasks();
        $this->runEraTask();
        $this->fillEraTaskPool();
        $this->scheduleNextEraTick();
    }


    /**
     * @param bool $ending
     * @return void
     */
    protected function initConfig(bool $ending = false): void
    {
        if ($ending) {
            Cache::set('config', $this->config);
        } else {
            // print_r(Cache::get('config'));
            $this->config = ($tmp = Cache::get('config')) ? $tmp : [];
            //
            $keys = ['campaign_draw_disabled_ids', 'campaign_draw_credit_claim_queue', 'campaign_draw_credit_refresh_queue', 'campaign_draw_execute_queue', 'wait_era_tasks', 'pending_era_tasks', 'done_era_task_keys', 'era_task_states', 'era_pool_state'];
            foreach ($keys as $key) {
                if (!isset($this->config[$key]) || !is_array($this->config[$key])) {
                    $this->config[$key] = [];
                }
            }

            if (!isset($this->config['era_pool_state']['lane_next_run_at']) || !is_array($this->config['era_pool_state']['lane_next_run_at'])) {
                $this->config['era_pool_state']['lane_next_run_at'] = [];
            }

            $this->dropOutdatedCampaignRuntimeState();
        }
    }

    protected function dropOutdatedCampaignRuntimeState(): void
    {
        $invalidDrawQueue = false;
        foreach ([
            'campaign_draw_credit_claim_queue',
            'campaign_draw_credit_refresh_queue',
            'campaign_draw_execute_queue',
        ] as $queueKey) {
            foreach ($this->config[$queueKey] as $item) {
                if (!is_array($item) || trim((string)($item['draw_id'] ?? '')) === '') {
                    $invalidDrawQueue = true;
                    break 2;
                }
            }
        }

        if ($invalidDrawQueue) {
            $this->config['campaign_draw_credit_claim_queue'] = [];
            $this->config['campaign_draw_credit_refresh_queue'] = [];
            $this->config['campaign_draw_execute_queue'] = [];
            $today = date('Y-m-d');
            unset(
                $this->config[$today]['campaign_draw_credit_claim'],
                $this->config[$today]['campaign_draw_credit_refresh'],
                $this->config[$today]['campaign_draw_execute'],
                $this->config[$today]['fetch']
            );
        }

        $invalidEraQueue = false;
        foreach (['wait_era_tasks', 'pending_era_tasks'] as $queueKey) {
            foreach ($this->config[$queueKey] as $item) {
                if (!is_array($item) || !is_array($item['campaign'] ?? null)) {
                    $invalidEraQueue = true;
                    break 2;
                }
            }
        }

        if ($invalidEraQueue) {
            $this->config['wait_era_tasks'] = [];
            $this->config['pending_era_tasks'] = [];
            $today = date('Y-m-d');
            unset($this->config[$today]['era'], $this->config[$today]['fetch']);
        }
    }

    protected function isFinishedToday(): bool
    {
        $today = date('Y-m-d');

        return isset(
            $this->config[$today]['campaign_draw_credit_claim'],
            $this->config[$today]['campaign_draw_credit_refresh'],
            $this->config[$today]['campaign_draw_execute'],
            $this->config[$today]['era']
        );
    }

    /**
     * 获取远程数据
     * @return void
     */
    protected function fetchRemoteInfos(): void
    {
        if (isset($this->config[date("Y-m-d")]['fetch'])) return;
        //
        $this->config['campaign_draw_credit_claim_queue'] = [];
        $this->config['campaign_draw_credit_refresh_queue'] = [];
        $this->config['campaign_draw_execute_queue'] = [];
        $this->config['wait_era_tasks'] = [];
        $this->config['pending_era_tasks'] = [];
        $this->config['done_era_task_keys'] = [];
        $localData = $this->loadLocalActivityInfos();
        if ($localData !== null) {
            Log::info('转盘活动: 使用本地活动资源 resources/activity_infos.json');
            $this->_fetchRemoteInfos($localData);
            return;
        }

        $url = 'aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL2xrZW1lL0JpbGlIZWxwZXItcGVyc29uYWwvbWFzdGVyL3Jlc291cmNlcy9hY3Rpdml0eV9pbmZvcy5qc29u';
        $url = GhProxy::mirror(base64_decode($url));
        $response = Request::getJson(true, 'other', $url);
        Log::info('转盘活动: 本地活动资源不可用，已回退到线上活动资源');
        $this->_fetchRemoteInfos($response['data']);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function loadLocalActivityInfos(): ?array
    {
        $path = rtrim(str_replace('\\', '/', $this->appContext()->appRoot()), '/') . '/resources/activity_infos.json';
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        try {
            $raw = file_get_contents($path);
            if (!is_string($raw) || trim($raw) === '') {
                return null;
            }

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $data = $decoded['data'] ?? null;
            if (!is_array($data)) {
                return null;
            }

            return array_values(array_filter($data, static fn (mixed $item): bool => is_array($item)));
        } catch (\Throwable $throwable) {
            Log::warning("转盘活动: 读取本地活动资源失败 {$throwable->getMessage()}");
            return null;
        }
    }

    /**
     * 获取远程数据
     * @param array $data
     * @return void
     */
    protected function _fetchRemoteInfos(array $data): void
    {
        $new_data = [];
        $eraCount = 0;
        $skippedCount = 0;
        $invalidEraCount = 0;
        $queuedEraTaskCount = 0;
        //
        foreach ($data as $value) {
            if ($this->isEraActivityUrl((string)($value['url'] ?? ''))) {
                $page = $this->inspectEraActivityPage($value);
                if ($page !== null) {
                    $campaign = $this->buildCampaign($value, $page);
                    $eraCount++;
                    $value['era'] = $page->toArray();
                    $this->logEraActivitySummary($page);
                    if ($page->isExpired()) {
                        $skippedCount++;
                        Log::info("转盘活动: {$page->title} ERA 活动已结束，已自动跳过");
                        continue;
                    }
                    if ($page->isNotStarted()) {
                        $skippedCount++;
                        Log::info("转盘活动: {$page->title} ERA 活动未开始，已自动跳过");
                        continue;
                    }

                    $invalidReason = $this->detectInvalidEraActivityPage($value, $page);
                    if ($invalidReason !== null) {
                        $invalidEraCount++;
                        Log::warning("转盘活动: {$this->formatEraActivityTitle($value, $page)} 活动页无效，已自动跳过 {$invalidReason}");
                        continue;
                    }

                    $queuedEraTaskCount += $this->queueEraTasks($page, $campaign);
                    if (in_array($campaign->drawId, $this->config['campaign_draw_disabled_ids'], true)) {
                        continue;
                    }
                    $new_data[] = $campaign;
                    continue;
                }
            }
            if (in_array($this->campaignDrawIdFromResource($value), $this->config['campaign_draw_disabled_ids'], true)) continue;
            $new_data[] = $this->buildCampaign($value);
        }
        // 获取乱序数据
        shuffle($new_data);
        $this->config['campaign_draw_credit_claim_queue'] = [];
        foreach ($new_data as $campaign) {
            $this->enqueueCampaignDrawCreditClaim($campaign);
        }
        $this->sortPendingEraTaskQueue();
        if ($this->config['wait_era_tasks'] === [] && $this->config['pending_era_tasks'] === []) {
            $this->config[date("Y-m-d")]['era'] = true;
        }
        //
        Log::info("转盘活动: 获取到有效远程数据" . count($new_data) . "条");
        if ($eraCount > 0) {
            Log::info("转盘活动: 已识别任务页活动 {$eraCount} 条，抽奖阶段待接入 {$skippedCount} 条");
            Log::info("转盘活动: 已加入任务执行队列 {$queuedEraTaskCount} 条");
        }
        if ($invalidEraCount > 0) {
            Log::info("转盘活动: 已过滤无效活动页 {$invalidEraCount} 条");
        }
        //
        $this->config[date("Y-m-d")]['fetch'] = true;
    }

    protected function isEraActivityUrl(string $url): bool
    {
        return str_contains($url, '/blackboard/era/');
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function hasCampaignDrawBackend(array $value, ?EraActivityPage $page = null): bool
    {
        return $this->campaignDrawIdFromResource($value, $page) !== '';
    }

    protected function campaignDrawIdFromResource(array $value, ?EraActivityPage $page = null): string
    {
        $lotteryId = trim((string)($page?->lotteryId ?? ''));
        if ($lotteryId !== '') {
            return $lotteryId;
        }

        return trim((string)($value['sid'] ?? ''));
    }

    /**
     * @param array<string, mixed> $resource
     * @return array<string, string>
     */
    protected function campaignDrawRequestPayloadForResource(array $resource, ?EraActivityPage $page = null): array
    {
        return [
            'sid' => $this->campaignDrawIdFromResource($resource, $page),
            'url' => trim((string)($resource['url'] ?? '')),
            'title' => $page?->title !== null && $page->title !== ''
                ? $page->title
                : trim((string)($resource['title'] ?? '')),
        ];
    }

    protected function buildCampaign(array $resource, ?EraActivityPage $page = null): ActivityCampaign
    {
        return new ActivityCampaign(
            $page?->title !== null && $page->title !== ''
                ? $page->title
                : trim((string)($resource['title'] ?? '')),
            trim((string)($resource['url'] ?? '')),
            '',
            '',
            $page !== null ? 'era_task' : '',
            $this->hasCampaignDrawBackend($resource, $page) ? self::DRAW_BACKEND_SID_LOTTERY : '',
            $page?->activityId ?? '',
            $page?->lotteryId ?? '',
            $this->campaignDrawIdFromResource($resource, $page),
        );
    }

    protected function campaignFromEraTask(array $item, EraActivityTask $task): ActivityCampaign
    {
        $campaign = ActivityCampaign::fromArray(is_array($item['campaign'] ?? null) ? $item['campaign'] : []);

        if ($task->taskId === '') {
            return $campaign;
        }

        return new ActivityCampaign(
            $campaign->title,
            $campaign->activityUrl,
            'https://www.bilibili.com/blackboard/era/award-exchange.html?task_id=' . $task->taskId,
            $campaign->recordUrl,
            $campaign->taskBackend !== '' ? $campaign->taskBackend : 'era_task',
            $campaign->drawBackend,
            $campaign->activityId,
            $campaign->lotteryId,
            $campaign->drawId,
        );
    }

    protected function formatCampaignNotice(ActivityCampaign $campaign, string $summary, bool $manualActionRequired = false): string
    {
        $lines = [];
        if ($campaign->title !== '') {
            $lines[] = '活动: ' . $campaign->title;
        }
        $lines[] = '结果: ' . $summary;
        if ($manualActionRequired) {
            $lines[] = '处理: 需人工确认，不会自动填写资料';
        }
        if ($campaign->hasActivityUrl()) {
            $lines[] = '活动地址: ' . $campaign->activityUrl;
        }
        if ($campaign->hasRewardUrl()) {
            $lines[] = '领奖入口: ' . $campaign->rewardUrl;
        }
        if ($campaign->hasRecordUrl()) {
            $lines[] = '记录入口: ' . $campaign->recordUrl;
        }

        return implode(PHP_EOL, $lines);
    }

    protected function activityLotteryTaskEnabled(string $key, bool $default = true): bool
    {
        return (bool)$this->config('activity_lottery.' . $key, $default, 'bool');
    }

    protected function isEraTaskDaily(EraActivityTask $task): bool
    {
        $taskName = trim($task->taskName);
        if (str_contains($taskName, '首次') || str_contains($taskName, '首日')) {
            return false;
        }

        if (str_contains($taskName, '每日') || str_contains($taskName, '每天') || str_contains($taskName, '当日')) {
            return true;
        }

        return $task->periodType === 1;
    }

    protected function eraTaskCycle(EraActivityTask $task): string
    {
        return $this->isEraTaskDaily($task) ? date('Y-m-d') : 'once';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEraTaskState(array $item, EraActivityTask $task): array
    {
        $stateKey = (string)($item['key'] ?? '');
        $cycle = $this->eraTaskCycle($task);
        $stored = $stateKey !== '' && is_array($this->config['era_task_states'][$stateKey] ?? null)
            ? $this->config['era_task_states'][$stateKey]
            : [];

        if (($stored['cycle'] ?? null) !== $cycle) {
            $stored = [];
        }

        return array_replace([
            'cycle' => $cycle,
            'completed' => false,
            'action_done' => false,
            'next_check_at' => 0,
            'local_watch_seconds' => 0,
            'temporary_follow_uids' => [],
            'follow_target_index' => 0,
            'cleanup_pending' => false,
            'cleanup_index' => 0,
        ], $stored);
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function saveEraTaskState(array $item, array $state): void
    {
        $stateKey = (string)($item['key'] ?? '');
        if ($stateKey === '') {
            return;
        }

        $this->config['era_task_states'][$stateKey] = $state;
    }

    protected function clearEraTaskState(array $item): void
    {
        $stateKey = (string)($item['key'] ?? '');
        if ($stateKey === '') {
            return;
        }

        unset($this->config['era_task_states'][$stateKey]);
    }

    protected function eraTaskStatusDelaySeconds(EraActivityTask $task): int
    {
        return match ($task->capability) {
            EraActivityTask::CAPABILITY_FOLLOW,
            EraActivityTask::CAPABILITY_SHARE => 120,
            EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED,
            EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC,
            EraActivityTask::CAPABILITY_WATCH_LIVE => 180,
            default => 120,
        };
    }

    protected function eraWatchBufferSeconds(int $thresholdSeconds): int
    {
        if ($thresholdSeconds >= 3600) {
            return 300;
        }

        if ($thresholdSeconds >= 1800) {
            return 180;
        }

        if ($thresholdSeconds >= 600) {
            return 120;
        }

        if ($thresholdSeconds >= 180) {
            return 60;
        }

        return 30;
    }

    protected function eraTaskLane(EraActivityTask $task, array $state, ?array $item = null): string
    {
        if ((bool)($state['cleanup_pending'] ?? false)) {
            return 'follow';
        }

        if ((bool)($state['action_done'] ?? false)) {
            return 'status';
        }

        return match ($task->capability) {
            EraActivityTask::CAPABILITY_SHARE => 'share',
            EraActivityTask::CAPABILITY_FOLLOW => 'follow',
            EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED,
            EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC => 'watch_video',
            EraActivityTask::CAPABILITY_WATCH_LIVE => is_array($item['live_session'] ?? null) ? 'watch_live' : 'watch_live_init',
            default => 'default',
        };
    }

    protected function eraLaneCooldownSeconds(string $lane): int
    {
        return match ($lane) {
            'share' => 8,
            'follow' => self::ERA_FOLLOW_STEP_DELAY_SECONDS,
            'watch_video' => 5,
            'watch_live_init' => 3,
            'watch_live' => 0,
            'status' => 10,
            default => 5,
        };
    }

    protected function eraLaneBlockedUntil(string $lane): int
    {
        return (int)($this->config['era_pool_state']['lane_next_run_at'][$lane] ?? 0);
    }

    protected function reserveEraLane(string $lane): void
    {
        $cooldown = $this->eraLaneCooldownSeconds($lane);
        if ($cooldown <= 0) {
            return;
        }

        $this->config['era_pool_state']['lane_next_run_at'][$lane] = time() + $cooldown;
    }

    protected function activeEraLiveTaskKey(): string
    {
        return trim((string)($this->config['era_pool_state']['active_live_task_key'] ?? ''));
    }

    protected function setActiveEraLiveTaskKey(string $taskKey): void
    {
        $taskKey = trim($taskKey);
        if ($taskKey === '') {
            unset($this->config['era_pool_state']['active_live_task_key']);
            return;
        }

        $this->config['era_pool_state']['active_live_task_key'] = $taskKey;
    }

    protected function clearActiveEraLiveTaskKey(?string $taskKey = null): void
    {
        $current = $this->activeEraLiveTaskKey();
        if ($current === '') {
            return;
        }

        if ($taskKey !== null && $taskKey !== '' && $current !== $taskKey) {
            return;
        }

        unset($this->config['era_pool_state']['active_live_task_key']);
    }

    protected function requeueEraTaskByLane(array $item, EraActivityTask $task, int $blockedUntil): void
    {
        if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE) {
            $item['live_due_at'] = $blockedUntil;
        } else {
            $item['due_at'] = $blockedUntil;
        }

        $this->enqueueEraTask($item, $task);
    }

    protected function eraTaskItemDueAt(array $item, EraActivityTask $task): int
    {
        return $task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE
            ? (int)($item['live_due_at'] ?? 0)
            : (int)($item['due_at'] ?? 0);
    }

    protected function enqueueEraTask(array $item, EraActivityTask $task): void
    {
        $key = (string)($item['key'] ?? '');
        if ($key !== '') {
            $this->purgeEraTaskFromQueues($key);
        }

        $this->config['pending_era_tasks'][] = $item;
    }

    protected function isEraAutoCapabilityEnabled(EraActivityTask $task): bool
    {
        return match ($task->capability) {
            EraActivityTask::CAPABILITY_SHARE => $this->activityLotteryTaskEnabled('share'),
            EraActivityTask::CAPABILITY_FOLLOW => $this->activityLotteryTaskEnabled('follow'),
            EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED,
            EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC => $this->activityLotteryTaskEnabled('watch_video'),
            EraActivityTask::CAPABILITY_WATCH_LIVE => $this->activityLotteryTaskEnabled('watch_live'),
            EraActivityTask::CAPABILITY_LIKE_TOPIC,
            EraActivityTask::CAPABILITY_COIN_TOPIC,
            EraActivityTask::CAPABILITY_COMMENT_TOPIC => false,
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function inspectEraActivityPage(array $value): ?EraActivityPage
    {
        $url = trim((string)($value['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        $cacheKey = 'era_page_' . substr(sha1($url . '|' . (string)($value['update_time'] ?? '')), 0, 16);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $page = EraActivityPage::fromArray($cached);
            if ($page->lotteryId !== '') {
                return $page;
            }
        }

        try {
            $html = Request::get('other', $url);
            $page = (new EraActivityPageParser())->parse($html);
            if ($page !== null) {
                $page = $this->resolveEraActivityTimeRange($page);
                Cache::set($cacheKey, $page->toArray());
            }

            return $page;
        } catch (\Throwable $throwable) {
            Log::warning("转盘活动: 活动页解析失败 {$url} -> {$throwable->getMessage()}");
            return null;
        }
    }

    /**
     * @param array<string, mixed> $resource
     */
    protected function detectInvalidEraActivityPage(array $resource, EraActivityPage $page): ?string
    {
        if (!$this->hasCampaignDrawBackend($resource, $page)) {
            return '缺少抽奖ID';
        }

        return $this->probeCampaignDrawAvailability($resource, $page);
    }

    /**
     * @param array<string, mixed> $resource
     */
    protected function probeCampaignDrawAvailability(array $resource, EraActivityPage $page): ?string
    {
        $response = ApiActivity::myTimes($this->campaignDrawRequestPayloadForResource($resource, $page));
        $code = (int)($response['code'] ?? -1);
        if (!in_array($code, [170001, 175003, 170405], true)) {
            return null;
        }

        return "抽奖活动不可用 Error: {$code} -> " . (string)($response['message'] ?? '');
    }

    /**
     * @param array<string, mixed> $resource
     */
    protected function formatEraActivityTitle(array $resource, EraActivityPage $page): string
    {
        $title = trim($page->title);
        if ($title !== '') {
            return $title;
        }

        $title = trim((string)($resource['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return trim((string)($resource['url'] ?? ''));
    }

    protected function logEraActivitySummary(EraActivityPage $page): void
    {
        $capabilities = [];
        foreach ($page->taskCapabilitySummary() as $capability => $count) {
            $capabilities[] = "{$capability}={$count}";
        }

        $parts = [];
        if ($page->followUids !== []) {
            $parts[] = 'follow=' . count($page->followUids);
        }
        if ($page->videoIds !== []) {
            $parts[] = 'video=' . count($page->videoIds);
        }
        if ($page->liveRoomIds !== []) {
            $parts[] = 'live=' . count($page->liveRoomIds);
        }
        if ($page->topicIds !== []) {
            $parts[] = 'topic=' . count($page->topicIds);
        }
        if ($page->startTime > 0 || $page->endTime > 0) {
            $parts[] = 'window=' . $this->formatEraTimeRange($page);
        }

        $summary = $capabilities !== [] ? implode(', ', $capabilities) : 'none';
        $targets = $parts !== [] ? implode(', ', $parts) : 'none';

        Log::info("转盘活动: 解析 ERA 活动 {$page->title} 任务[{$summary}] 目标[{$targets}]");
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function queueEraTasks(EraActivityPage $page, ActivityCampaign $campaign): int
    {
        $queued = 0;
        $tasks = $page->tasks;
        usort($tasks, fn (EraActivityTask $left, EraActivityTask $right): int => $this->eraTaskPriority($left) <=> $this->eraTaskPriority($right));

        foreach ($tasks as $task) {
            if (!$this->shouldQueueEraTask($task)) {
                continue;
            }

            $item = $this->buildEraTaskQueueItem($page, $task, $campaign);
            if ($item === null) {
                continue;
            }

            $state = $this->getEraTaskState($item, $task);
            if ((bool)($state['completed'] ?? false)) {
                continue;
            }

            $key = $item['key'];
            if (in_array($key, $this->config['done_era_task_keys'], true) || $this->hasEraTaskQueued($key)) {
                continue;
            }

            $this->config['pending_era_tasks'][] = $item;
            $queued++;
        }

        return $queued;
    }

    protected function shouldQueueEraTask(EraActivityTask $task): bool
    {
        if ($task->taskId === '') {
            return false;
        }

        if ($task->taskAwardType === 1 && $task->taskStatus === 2) {
            return true;
        }

        if (!$task->isRunnableNow()) {
            return false;
        }

        if ($task->taskStatus >= 3 || ($task->taskStatus >= 2 && $task->taskAwardType !== 1)) {
            return false;
        }

        if (!$this->isEraAutoCapabilityEnabled($task)) {
            return false;
        }

        return match ($task->capability) {
            EraActivityTask::CAPABILITY_SHARE => $task->counter !== '',
            EraActivityTask::CAPABILITY_FOLLOW => $task->targetUids !== [],
            EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED => $task->targetArchives !== [] || $this->hasNumericTargetVideoId($task),
            EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC => $task->topicId !== '',
            EraActivityTask::CAPABILITY_WATCH_LIVE => $task->hasLiveTarget(),
            default => false,
        };
    }

    protected function eraTaskPriority(EraActivityTask $task): int
    {
        if ($task->taskAwardType === 1 && $task->taskStatus === 2) {
            return 5;
        }

        return match ($task->capability) {
            EraActivityTask::CAPABILITY_SHARE => 10,
            EraActivityTask::CAPABILITY_FOLLOW => 20,
            EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED => 25,
            EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC => 26,
            EraActivityTask::CAPABILITY_WATCH_LIVE => 30,
            default => 99,
        };
    }

    protected function buildEraTaskQueueItem(EraActivityPage $page, EraActivityTask $task, ActivityCampaign $campaign): ?array
    {
        if ($task->taskId === '') {
            return null;
        }

        return [
            'key' => $this->buildEraTaskKey($page, $task, $campaign->activityUrl),
            'campaign' => $campaign->toArray(),
            'task' => $task->toArray(),
            'attempts' => 0,
            'due_at' => 0,
        ];
    }

    protected function buildEraTaskKey(EraActivityPage $page, EraActivityTask $task, string $url): string
    {
        $base = implode('|', [
            $page->pageId,
            $page->activityId,
            $page->lotteryId,
            $url,
            $task->taskId,
            $task->capability,
        ]);

        return substr(sha1($base), 0, 20);
    }

    protected function hasEraTaskQueued(string $key): bool
    {
        foreach (['wait_era_tasks', 'pending_era_tasks'] as $queueKey) {
            foreach ($this->config[$queueKey] as $item) {
                if (is_array($item) && (string)($item['key'] ?? '') === $key) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function sortPendingEraTaskQueue(): void
    {
        usort($this->config['pending_era_tasks'], function (mixed $left, mixed $right): int {
            $leftTask = EraActivityTask::fromArray(is_array($left) && is_array($left['task'] ?? null) ? $left['task'] : []);
            $rightTask = EraActivityTask::fromArray(is_array($right) && is_array($right['task'] ?? null) ? $right['task'] : []);

            $leftDueAt = is_array($left) ? $this->eraTaskItemDueAt($left, $leftTask) : 0;
            $rightDueAt = is_array($right) ? $this->eraTaskItemDueAt($right, $rightTask) : 0;
            $leftDueAt = max(0, $leftDueAt);
            $rightDueAt = max(0, $rightDueAt);

            if ($leftDueAt !== $rightDueAt) {
                return $leftDueAt <=> $rightDueAt;
            }

            return $this->eraTaskPriority($leftTask) <=> $this->eraTaskPriority($rightTask);
        });
    }

    protected function fillEraTaskPool(): void
    {
        if ($this->config['pending_era_tasks'] === []) {
            return;
        }

        $this->sortPendingEraTaskQueue();
        $now = time();
        while (count($this->config['wait_era_tasks']) < self::ERA_POOL_CAPACITY && $this->config['pending_era_tasks'] !== []) {
            $first = $this->config['pending_era_tasks'][0] ?? null;
            $firstTask = EraActivityTask::fromArray(is_array($first) && is_array($first['task'] ?? null) ? $first['task'] : []);
            if (!is_array($first) || $firstTask->taskId === '') {
                array_shift($this->config['pending_era_tasks']);
                continue;
            }

            $firstDueAt = $this->eraTaskItemDueAt($first, $firstTask);
            if ($firstDueAt > $now) {
                break;
            }

            $item = array_shift($this->config['pending_era_tasks']);
            if (!is_array($item)) {
                continue;
            }

            $task = EraActivityTask::fromArray(is_array($item['task'] ?? null) ? $item['task'] : []);
            if ($task->taskId === '') {
                continue;
            }

            $state = $this->getEraTaskState($item, $task);
            if ((bool)($state['completed'] ?? false) && !(bool)($state['cleanup_pending'] ?? false)) {
                continue;
            }

            if ((bool)($state['action_done'] ?? false) && (int)($state['next_check_at'] ?? 0) > 0) {
                if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE) {
                    $item['live_due_at'] = (int)$state['next_check_at'];
                } else {
                    $item['due_at'] = (int)$state['next_check_at'];
                }
            }

            $this->config['wait_era_tasks'][] = $item;
        }
    }

    protected function runEraTask(): void
    {
        $today = date('Y-m-d');
        if (isset($this->config[$today]['era'])) {
            return;
        }

        if ($this->config['wait_era_tasks'] === []) {
            $this->fillEraTaskPool();
            if ($this->config['wait_era_tasks'] === []) {
                $this->finalizeEraDayState();
                return;
            }
        }

        $scan = count($this->config['wait_era_tasks']);
        $processed = 0;
        while ($scan-- > 0 && $processed < self::ERA_NON_LIVE_BATCH_LIMIT) {
            $item = array_shift($this->config['wait_era_tasks']);
            if (!is_array($item)) {
                continue;
            }

            $item['attempts'] = (int)($item['attempts'] ?? 0);
            $task = EraActivityTask::fromArray(is_array($item['task'] ?? null) ? $item['task'] : []);
            if ($task->taskId === '') {
                Log::warning('转盘活动: ERA 任务缺少 task_id，已跳过');
                $this->markEraTaskDone($item, $task);
                $processed++;
                continue;
            }

            $state = $this->getEraTaskState($item, $task);
            if ((bool)($state['completed'] ?? false)) {
                if ($this->processEraTaskCleanup($item, $task, $state)) {
                    $processed++;
                    continue;
                }

                $this->finalizeEraTaskDone($item);
                $processed++;
                continue;
            }

            if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE) {
                if ((bool)($state['action_done'] ?? false) && (int)($state['next_check_at'] ?? 0) > time()) {
                    $item['live_due_at'] = (int)$state['next_check_at'];
                }
                $this->enqueueEraTask($item, $task);
                continue;
            }

            if ((bool)($state['action_done'] ?? false) && (int)($state['next_check_at'] ?? 0) > time()) {
                $item['due_at'] = (int)$state['next_check_at'];
                $this->enqueueEraTask($item, $task);
                continue;
            }

            if (!$this->isEraAutoCapabilityEnabled($task) && !$this->isEraTaskReadyToClaim($task, null)) {
                Log::info("转盘活动: {$this->formatEraTaskLabel($item, $task)} 当前任务自动执行已关闭，已跳过");
                $this->markEraTaskDone($item, $task);
                $processed++;
                continue;
            }

            if (!$this->isEraTaskDue($item)) {
                $this->enqueueEraTask($item, $task);
                continue;
            }

            $lane = $this->eraTaskLane($task, $state, $item);
            $laneBlockedUntil = $this->eraLaneBlockedUntil($lane);
            if ($laneBlockedUntil > time()) {
                $this->requeueEraTaskByLane($item, $task, $laneBlockedUntil);
                continue;
            }
            $this->reserveEraLane($lane);

            $progress = $this->fetchEraTaskProgress($item, $task);
            if ($progress !== null && $this->isEraTaskProgressCompleted($task, $progress)) {
                $this->markEraTaskDone($item, $task);
                $processed++;
                continue;
            }

            if ($this->handleEraTaskReadyToClaim($item, $task, $progress)) {
                $processed++;
                continue;
            }

            if ((bool)($state['action_done'] ?? false)) {
                $this->deferEraTaskForStatusSync(
                    $item,
                    $task,
                    $state,
                    '任务状态同步中',
                    null,
                    $this->shouldEmitEraStateLog($state, 'status_sync_pending', 1800)
                );
                $processed++;
                continue;
            }

            $processed++;
            match ($task->capability) {
                EraActivityTask::CAPABILITY_SHARE => $this->runEraShareTask($item, $task),
                EraActivityTask::CAPABILITY_FOLLOW => $this->runEraFollowTask($item, $task),
                EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED => $this->runEraWatchVideoTask($item, $task),
                EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC => $this->runEraWatchTopicVideoTask($item, $task),
                EraActivityTask::CAPABILITY_WATCH_LIVE => $this->runEraWatchLiveTask($item, $task),
                default => $this->skipEraTask($item, $task, '当前能力暂未接入自动执行'),
            };
        }

        $this->finalizeEraDayState();
    }

    protected function runEraShareTask(array $item, EraActivityTask $task): void
    {
        $state = $this->getEraTaskState($item, $task);
        Log::info("转盘活动: {$this->formatEraTaskLabel($item, $task)} 开始执行分享任务");
        $campaign = $this->campaignFromEraTask($item, $task);
        $response = ApiActivity::sendPoints(
            $task->taskId,
            $task->counter,
            $campaign->activityUrl !== '' ? $campaign->activityUrl : 'https://www.bilibili.com/'
        );
        if (($response['code'] ?? -1) !== 0) {
            Log::warning("转盘活动: {$this->formatEraTaskLabel($item, $task)} 分享上报失败 Error: {$response['code']} -> {$response['message']}");
        }

        $progress = $this->fetchEraTaskProgress($item, $task);
        if ($progress !== null && $this->isEraTaskProgressCompleted($task, $progress)) {
            $this->markEraTaskDone($item, $task);
            return;
        }

        if ($this->handleEraTaskReadyToClaim($item, $task, $progress)) {
            return;
        }

        $this->deferEraTaskForStatusSync(
            $item,
            $task,
            $state,
            '分享已执行，等待任务状态同步',
            null,
            $this->shouldEmitEraStateLog($state, 'share_status_sync', 1800)
        );
    }

    protected function runEraFollowTask(array $item, EraActivityTask $task): void
    {
        $state = $this->getEraTaskState($item, $task);
        Log::info("转盘活动: {$this->formatEraTaskLabel($item, $task)} 开始执行关注任务");
        $uids = array_values($task->targetUids);
        $index = max(0, (int)($state['follow_target_index'] ?? 0));
        if (isset($uids[$index])) {
            $uid = (string)$uids[$index];
            $response = ApiRelation::follow((int)$uid, ApiRelation::SOURCE_ACTIVITY_PAGE);
            if (($response['code'] ?? -1) === 0) {
                $temporary = is_array($state['temporary_follow_uids'] ?? null) ? $state['temporary_follow_uids'] : [];
                $temporary[] = $uid;
                $state['temporary_follow_uids'] = array_values(array_unique(array_map('strval', $temporary)));
                $state['follow_target_index'] = $index + 1;
                $this->saveEraTaskState($item, $state);
            } elseif ($this->isEraFollowResponseSuccessful($response)) {
                $state['follow_target_index'] = $index + 1;
                $this->saveEraTaskState($item, $state);
            } else {
                Log::warning("转盘活动: {$this->formatEraTaskLabel($item, $task)} 关注 {$uid} 失败 Error: {$response['code']} -> {$response['message']}");
                $this->retryEraTask($item, $task, '关注失败', 60);
                return;
            }

            if ((int)($state['follow_target_index'] ?? 0) < count($uids)) {
                $this->deferEraTask($item, $task, '关注节流，准备处理下一位账号', self::ERA_FOLLOW_STEP_DELAY_SECONDS, false);
                return;
            }
        }

        $progress = $this->fetchEraTaskProgress($item, $task);
        if ($progress !== null && $this->isEraTaskProgressCompleted($task, $progress)) {
            $this->markEraTaskDone($item, $task);
            return;
        }

        if ($this->handleEraTaskReadyToClaim($item, $task, $progress)) {
            return;
        }

        $this->deferEraTaskForStatusSync(
            $item,
            $task,
            $state,
            '关注已执行，等待任务状态同步',
            null,
            $this->shouldEmitEraStateLog($state, 'follow_status_sync', 1800)
        );
    }

    protected function runEraWatchVideoTask(array $item, EraActivityTask $task): void
    {
        $state = $this->getEraTaskState($item, $task);
        $label = $this->formatEraTaskLabel($item, $task);
        $archive = is_array($item['watch_video_archive'] ?? null) ? $item['watch_video_archive'] : null;
        $started = (bool)($item['watch_video_started'] ?? false);

        if ($archive === null) {
            if ($task->capability === EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED) {
                $archive = $this->currentFixedArchive($item, $task);
            }
            if ($archive === null) {
                $archive = $this->eraVideoWatchService()->resolveArchive($task);
            }
            $archive = is_array($archive) ? $this->eraVideoWatchService()->normalizeArchive($archive) : null;
            if ($archive === null) {
                $this->skipEraTask($item, $task, '无法解析视频稿件信息');
                return;
            }

            $item['watch_video_archive'] = $archive;
        } else {
            $archive = $this->eraVideoWatchService()->normalizeArchive($archive);
            if ($archive === null) {
                $this->retryEraTask($item, $task, '视频稿件信息补全失败', 60);
                return;
            }

            $item['watch_video_archive'] = $archive;
        }

        if (!$started) {
            $shouldLog = $this->shouldEmitEraStateLog($state, 'watch_video_start', 1800);
            $this->saveEraTaskState($item, $state);
            if ($shouldLog) {
                Log::info("转盘活动: {$label} 开始观看视频任务");
            }
            $session = trim((string)($item['watch_video_session'] ?? ''));
            if ($session === '') {
                try {
                    $session = strtolower(bin2hex(random_bytes(16)));
                } catch (\Throwable) {
                    $session = strtolower(md5(uniqid((string)mt_rand(), true)));
                }
                $item['watch_video_session'] = $session;
            }

            if (!$this->eraVideoWatchService()->start($archive, $session)) {
                $this->retryEraTask($item, $task, '视频观看初始化失败', 60);
                return;
            }

            $progress = $this->fetchEraTaskProgress($item, $task);
            $watchStartedAt = time();
            $remainingHint = max(0, (int)($item['watch_video_remaining_seconds'] ?? 0));
            if ($remainingHint > 0) {
                $duration = max(15, max(1, (int)($archive['duration'] ?? 0)) - 1);
                $waitSeconds = max(15, min($duration, $remainingHint));
            } else {
                $waitSeconds = $this->resolveEraWatchWaitSeconds($task, $archive, $progress);
            }
            $item['watch_video_started'] = true;
            $item['watch_video_started_at'] = $watchStartedAt;
            $item['watch_video_wait_seconds'] = $waitSeconds;
            $item['due_at'] = $watchStartedAt + $waitSeconds;
            unset($item['watch_video_remaining_seconds']);
            $this->enqueueEraTask($item, $task);
            $this->scheduleSoonerAfter((float)$waitSeconds);
            return;
        }

        $watchStartedAt = (int)($item['watch_video_started_at'] ?? time());
        $watchedSeconds = max(1, time() - $watchStartedAt);
        $session = trim((string)($item['watch_video_session'] ?? ''));
        if (!$this->eraVideoWatchService()->finish($archive, $watchStartedAt, $watchedSeconds, $session)) {
            $this->retryEraTask($item, $task, '视频观看收尾失败', 60);
            return;
        }
        unset($item['watch_video_started'], $item['watch_video_started_at'], $item['watch_video_wait_seconds']);

        $state['local_watch_seconds'] = (int)($state['local_watch_seconds'] ?? 0) + $watchedSeconds;
        $this->saveEraTaskState($item, $state);

        $observedCurrentSeconds = (int)$state['local_watch_seconds'];
        $targetSeconds = $this->resolveEraNextWatchTargetSeconds($task, null, $observedCurrentSeconds);
        $remainingSeconds = $targetSeconds > 0 ? max(0, $targetSeconds - $observedCurrentSeconds) : 0;
        if ($remainingSeconds > 0) {
            $shouldLog = $this->shouldEmitEraStateLog($state, 'watch_video_continue', 900);
            $this->saveEraTaskState($item, $state);
            $item['watch_video_remaining_seconds'] = $remainingSeconds;

            if ($task->capability === EraActivityTask::CAPABILITY_WATCH_VIDEO_FIXED) {
                $this->advanceFixedArchive($item, $task);
                unset($item['watch_video_archive']);
            } elseif ($task->capability === EraActivityTask::CAPABILITY_WATCH_VIDEO_TOPIC) {
                $this->moveToNextTopicArchive($item);
                unset($item['watch_video_archive']);
            }

            $this->deferEraTask(
                $item,
                $task,
                "观看进行中，本地累计 {$observedCurrentSeconds}/{$targetSeconds} 秒，还差 {$remainingSeconds} 秒",
                5,
                false,
                $shouldLog
            );
            return;
        }

        $progress = $this->fetchEraTaskProgress($item, $task);
        $serverCurrentSeconds = $this->eraWatchCurrentSeconds($task, $progress);
        $state['local_watch_seconds'] = max($serverCurrentSeconds, (int)($state['local_watch_seconds'] ?? 0));
        $this->saveEraTaskState($item, $state);
        if ($progress !== null && $this->isEraTaskProgressCompleted($task, $progress)) {
            $this->markEraTaskDone($item, $task);
            return;
        }

        if ($this->handleEraTaskReadyToClaim($item, $task, $progress)) {
            return;
        }

        $this->deferEraTaskForStatusSync(
            $item,
            $task,
            $state,
            '已完成目标观看，等待任务状态同步',
            null,
            $this->shouldEmitEraStateLog($state, 'watch_video_status_sync', 1800)
        );
    }

    protected function runEraWatchLiveTask(array $item, EraActivityTask $task): void
    {
        $state = $this->getEraTaskState($item, $task);
        $label = $this->formatEraTaskLabel($item, $task);
        $session = is_array($item['live_session'] ?? null) ? $item['live_session'] : null;

        try {
            if ($session === null) {
                $session = $this->eraLiveWatchService()->start($task->targetRoomIds, $task->targetAreaId, $task->targetParentAreaId);
                if ($session === null) {
                    $this->deferEraTask(
                        $item,
                        $task,
                        $task->hasLiveAreaTarget() ? '当前目标分区没有开播中的直播间' : '当前没有开播中的活动直播间',
                        600
                    );
                    return;
                }

                $item['live_session'] = $session;
                $this->setActiveEraLiveTaskKey((string)($item['key'] ?? ''));
                $delaySeconds = max(30, (int)($session['heartbeat_interval'] ?? 60));
                $item['live_due_at'] = (int)ceil(microtime(true) + $delaySeconds);
                $this->enqueueEraTask($item, $task);
                $roomDetail = $this->formatEraLiveRoomDetail($session);
                Log::info("转盘活动: {$label} 已接入直播间 {$roomDetail}，{$delaySeconds}秒后发送观看心跳");
                $this->scheduleSoonerAfter((float)$delaySeconds);
                return;
            }

            $session = $this->eraLiveWatchService()->heartbeat($session);
            $item['live_session'] = $session;
            unset($item['live_due_at']);
        } catch (\RuntimeException $exception) {
            $debugParts = [];
            if ($session !== null) {
                $debugParts[] = 'room=' . (string)($session['room_id'] ?? '-');
                $debugParts[] = 'seq=' . (string)($session['seq_id'] ?? '-');
                $debugParts[] = 'ets=' . (string)($session['ets'] ?? '-');
                $debugParts[] = 'interval=' . (string)($session['heartbeat_interval'] ?? '-');
                $debugParts[] = 'elapsed=' . (string)($session['_debug_elapsed_seconds'] ?? '-');
                $debugParts[] = 'last=' . (string)($session['last_heartbeat_at'] ?? '-');
                $debugParts[] = 'beat_at=' . (string)($session['_debug_heartbeat_at'] ?? '-');
                $debugParts[] = 'due_at=' . (string)($item['live_due_at'] ?? '-');
            }
            $this->clearActiveEraLiveTaskKey((string)($item['key'] ?? ''));
            unset($item['live_session']);
            unset($item['live_due_at']);
            Log::warning("转盘活动: {$label} 直播任务失败 {$exception->getMessage()}");
            if ($debugParts !== []) {
                Log::warning("转盘活动: {$label} 心跳调试 " . implode(' ', $debugParts));
            }
            $this->deferEraTask($item, $task, '直播观看链路异常，准备重新初始化', 120, true);
            return;
        }

        $delaySeconds = max(30, (int)($session['heartbeat_interval'] ?? 60));
        $state['local_watch_seconds'] = (int)($state['local_watch_seconds'] ?? 0) + $delaySeconds;
        $this->saveEraTaskState($item, $state);
        $observedCurrentSeconds = (int)$state['local_watch_seconds'];
        $targetSeconds = $this->resolveEraNextWatchTargetSeconds($task, null, $observedCurrentSeconds);
        if ($targetSeconds <= 0 || $observedCurrentSeconds >= $targetSeconds) {
            $this->clearActiveEraLiveTaskKey((string)($item['key'] ?? ''));
            unset($item['live_session'], $item['live_due_at']);
            $progress = $this->fetchEraTaskProgress($item, $task);
            $serverCurrentSeconds = $this->eraWatchCurrentSeconds($task, $progress);
            $state['local_watch_seconds'] = max($serverCurrentSeconds, (int)($state['local_watch_seconds'] ?? 0));
            $this->saveEraTaskState($item, $state);
            if ($progress !== null && $this->isEraTaskProgressCompleted($task, $progress)) {
                $this->markEraTaskDone($item, $task);
                return;
            }

            if ($this->handleEraTaskReadyToClaim($item, $task, $progress)) {
                return;
            }

            $this->deferEraTaskForStatusSync(
                $item,
                $task,
                $state,
                '已完成目标观看，等待任务状态同步',
                null,
                $this->shouldEmitEraStateLog($state, 'watch_live_status_sync', 1800)
            );
            return;
        }

        $item['live_due_at'] = (int)ceil(microtime(true) + $delaySeconds);
        $this->enqueueEraTask($item, $task);
        $shouldLog = $this->shouldEmitEraStateLog($state, 'watch_live_heartbeat', 1800);
        $this->saveEraTaskState($item, $state);
        if ($shouldLog) {
            if ($targetSeconds > 0) {
                Log::info("转盘活动: {$label} 直播进行中，本地累计 {$observedCurrentSeconds}/{$targetSeconds} 秒，{$delaySeconds}秒后继续心跳");
            } else {
                Log::info("转盘活动: {$label} 直播保活中，本地累计 {$observedCurrentSeconds} 秒，{$delaySeconds}秒后继续心跳");
            }
        }
        $this->scheduleSoonerAfter((float)$delaySeconds);
    }

    protected function runEraWatchTopicVideoTask(array $item, EraActivityTask $task): void
    {
        $archive = $this->currentTopicArchive($item, $task);
        if ($archive === null) {
            $this->skipEraTask($item, $task, '话题下未找到可观看视频');
            return;
        }

        $archive = $this->eraVideoWatchService()->normalizeArchiveIdentity($archive);
        if ($archive === null) {
            if ($this->moveToNextTopicArchive($item)) {
                $this->retryEraTask($item, $task, '当前话题稿件信息不完整，切换下一条稿件', self::ERA_TOPIC_STEP_DELAY_SECONDS, false);
                return;
            }

            $this->skipEraTask($item, $task, '话题下视频稿件信息不完整');
            return;
        }

        $item['watch_video_archive'] = $archive;
        $this->runEraWatchVideoTask($item, $task);
    }

    protected function skipEraTask(array $item, EraActivityTask $task, string $reason): void
    {
        Log::warning("转盘活动: {$this->formatEraTaskLabel($item, $task)} {$reason}，已跳过");
        $this->markEraTaskDone($item, $task);
    }

    protected function retryEraTask(array $item, EraActivityTask $task, string $reason, int $delaySeconds = 3, bool $incrementAttempts = true, bool $log = true): void
    {
        if ($incrementAttempts) {
            $item['attempts'] = (int)($item['attempts'] ?? 0) + 1;
            if ($item['attempts'] >= self::ERA_TASK_MAX_ATTEMPTS) {
                Log::warning("转盘活动: {$this->formatEraTaskLabel($item, $task)} {$reason}，重试 {$item['attempts']} 次后仍未完成，已跳过");
                $this->markEraTaskDone($item, $task);
                return;
            }
        }

        if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE) {
            $item['live_due_at'] = time() + max(1, $delaySeconds);
        } else {
            $item['due_at'] = time() + max(1, $delaySeconds);
        }
        $this->enqueueEraTask($item, $task);
        if ($log) {
            Log::info("转盘活动: {$this->formatEraTaskLabel($item, $task)} {$reason}，{$delaySeconds}秒后重试");
        }
        $this->scheduleSoonerAfter((float)$delaySeconds);
    }

    protected function deferEraTask(array $item, EraActivityTask $task, string $reason, int $delaySeconds, bool $incrementAttempts = false, bool $log = true): void
    {
        if ($incrementAttempts) {
            $item['attempts'] = (int)($item['attempts'] ?? 0) + 1;
            if ($item['attempts'] >= self::ERA_TASK_MAX_ATTEMPTS) {
                Log::warning("转盘活动: {$this->formatEraTaskLabel($item, $task)} {$reason}，重试 {$item['attempts']} 次后仍未完成，已跳过");
                $this->markEraTaskDone($item, $task);
                return;
            }
        }

        if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE) {
            $item['live_due_at'] = time() + max(1, $delaySeconds);
        } else {
            $item['due_at'] = time() + max(1, $delaySeconds);
        }
        $this->enqueueEraTask($item, $task);
        if ($log) {
            Log::info("转盘活动: {$this->formatEraTaskLabel($item, $task)} {$reason}，{$delaySeconds}秒后继续");
        }
        $this->scheduleSoonerAfter((float)$delaySeconds);
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function deferEraTaskForStatusSync(array $item, EraActivityTask $task, array $state, string $reason, ?int $delaySeconds = null, bool $log = true): void
    {
        $delaySeconds ??= $this->eraTaskStatusDelaySeconds($task);
        $state['action_done'] = true;
        $state['next_check_at'] = time() + max(1, $delaySeconds);
        $this->saveEraTaskState($item, $state);

        if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE) {
            $item['live_due_at'] = (int)$state['next_check_at'];
        } else {
            $item['due_at'] = (int)$state['next_check_at'];
        }
        $this->enqueueEraTask($item, $task);
        if ($log) {
            Log::info("转盘活动: {$this->formatEraTaskLabel($item, $task)} {$reason}，{$delaySeconds}秒后再检查状态");
        }
        $this->scheduleSoonerAfter((float)$delaySeconds);
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function shouldEmitEraStateLog(array &$state, string $marker, int $intervalSeconds = 300): bool
    {
        $markers = is_array($state['log_markers'] ?? null) ? $state['log_markers'] : [];
        $now = time();
        $lastAt = (int)($markers[$marker] ?? 0);
        if ($lastAt > 0 && ($now - $lastAt) < max(1, $intervalSeconds)) {
            return false;
        }

        $markers[$marker] = $now;
        $state['log_markers'] = $markers;

        return true;
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function processEraTaskCleanup(array $item, EraActivityTask $task, array $state): bool
    {
        if (!(bool)($state['cleanup_pending'] ?? false)) {
            return false;
        }

        $dueAt = (int)($state['next_check_at'] ?? 0);
        if ($dueAt > time()) {
            if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE) {
                $item['live_due_at'] = $dueAt;
            } else {
                $item['due_at'] = $dueAt;
            }
            $this->enqueueEraTask($item, $task);
            return true;
        }

        $uids = array_values(array_unique(array_map('strval', $state['temporary_follow_uids'] ?? [])));
        $index = max(0, (int)($state['cleanup_index'] ?? 0));
        if (!isset($uids[$index])) {
            $state['cleanup_pending'] = false;
            $state['temporary_follow_uids'] = [];
            $state['cleanup_index'] = 0;
            $this->saveEraTaskState($item, $state);
            return false;
        }

        $uid = $uids[$index];
        $label = $this->formatEraTaskLabel($item, $task);
        $response = ApiRelation::modify((int)$uid);
        if (($response['code'] ?? -1) !== 0) {
            Log::warning("转盘活动: {$label} 自动取消关注 {$uid} 失败 Error: {$response['code']} -> {$response['message']}");
            $state['next_check_at'] = time() + 60;
            $this->saveEraTaskState($item, $state);
            $item['due_at'] = (int)$state['next_check_at'];
            $this->enqueueEraTask($item, $task);
            return true;
        }

        Log::info("转盘活动: {$label} 已自动取消任务关注 {$uid}");
        $state['cleanup_index'] = $index + 1;
        $state['next_check_at'] = time() + self::ERA_UNFOLLOW_STEP_DELAY_SECONDS;
        $this->saveEraTaskState($item, $state);
        $item['due_at'] = (int)$state['next_check_at'];
        $this->enqueueEraTask($item, $task);
        return true;
    }

    protected function markEraTaskDone(array $item, ?EraActivityTask $task = null): void
    {
        $key = (string)($item['key'] ?? '');
        if ($task !== null) {
            $state = $this->getEraTaskState($item, $task);
            $state['completed'] = true;
            $state['action_done'] = true;
            $state['next_check_at'] = 0;
            $uids = array_values(array_unique(array_map('strval', $state['temporary_follow_uids'] ?? [])));
            if ($uids !== []) {
                $state['cleanup_pending'] = true;
                $state['cleanup_index'] = 0;
                $state['next_check_at'] = time() + self::ERA_UNFOLLOW_STEP_DELAY_SECONDS;
                $this->saveEraTaskState($item, $state);
                if ($key !== '') {
                    $this->purgeEraTaskFromQueues($key);
                    $this->clearActiveEraLiveTaskKey($key);
                }
                $item['due_at'] = (int)$state['next_check_at'];
                $this->enqueueEraTask($item, $task);
                $this->scheduleSoonerAfter((float)self::ERA_UNFOLLOW_STEP_DELAY_SECONDS);
                return;
            }

            $this->saveEraTaskState($item, $state);
        }

        $this->finalizeEraTaskDone($item);
    }

    protected function finalizeEraTaskDone(array $item): void
    {
        $key = (string)($item['key'] ?? '');
        if ($key !== '') {
            $this->purgeEraTaskFromQueues($key);
            $this->clearActiveEraLiveTaskKey($key);
        }

        if ($key !== '' && !in_array($key, $this->config['done_era_task_keys'], true)) {
            $this->config['done_era_task_keys'][] = $key;
        }

        $this->finalizeEraDayState();
    }

    protected function purgeEraTaskFromQueues(string $key): void
    {
        if ($key === '') {
            return;
        }

        foreach (['wait_era_tasks', 'pending_era_tasks'] as $queueKey) {
            $queue = is_array($this->config[$queueKey] ?? null) ? $this->config[$queueKey] : [];
            $this->config[$queueKey] = array_values(array_filter($queue, static function (mixed $queuedItem) use ($key): bool {
                if (!is_array($queuedItem)) {
                    return false;
                }

                return (string)($queuedItem['key'] ?? '') !== $key;
            }));
        }
    }

    protected function finalizeEraDayState(): void
    {
        if ($this->config['wait_era_tasks'] === [] && $this->config['pending_era_tasks'] === []) {
            $this->config[date('Y-m-d')]['era'] = true;
        }
    }

    protected function fetchEraTaskProgress(array $item, EraActivityTask $task): ?array
    {
        $response = ApiTask::totalV2([$task->taskId]);
        if (($response['code'] ?? -1) !== 0) {
            Log::warning("转盘活动: {$this->formatEraTaskLabel($item, $task)} 查询任务进度失败 Error: {$response['code']} -> {$response['message']}");
            return null;
        }

        $list = $response['data']['list'] ?? [];
        if (!is_array($list)) {
            return null;
        }

        foreach ($list as $progress) {
            if (is_array($progress) && (string)($progress['task_id'] ?? '') === $task->taskId) {
                return $progress;
            }
        }

        return null;
    }

    protected function resolveEraActivityTimeRange(EraActivityPage $page): EraActivityPage
    {
        if ($page->startTime > 0 || $page->endTime > 0) {
            return $page;
        }

        $taskId = $this->pickEraTimeRangeTaskId($page);
        if ($taskId === '') {
            return $page;
        }

        $response = ApiMission::info($taskId);
        if (($response['code'] ?? -1) !== 0 || !is_array($response['data'] ?? null)) {
            return $page;
        }

        $startTime = (int)($response['data']['stime'] ?? 0);
        $endTime = (int)($response['data']['etime'] ?? 0);
        if ($startTime <= 0 && $endTime <= 0) {
            return $page;
        }

        return $page->withTimeRange($startTime, $endTime);
    }

    protected function pickEraTimeRangeTaskId(EraActivityPage $page): string
    {
        foreach ($page->tasks as $task) {
            if ($task->taskId !== '') {
                return $task->taskId;
            }
        }

        return '';
    }

    protected function formatEraTimeRange(EraActivityPage $page): string
    {
        $start = $page->startTime > 0 ? date('Y-m-d H:i', $page->startTime) : '-';
        $end = $page->endTime > 0 ? date('Y-m-d H:i', $page->endTime) : '-';

        return "{$start}~{$end}";
    }

    protected function isEraTaskProgressCompleted(EraActivityTask $task, array $progress): bool
    {
        $taskStatus = (int)($progress['task_status'] ?? 0);
        if ($task->taskAwardType === 1) {
            return $taskStatus >= 3;
        }

        if ($taskStatus >= 2) {
            return true;
        }

        foreach (($progress['indicators'] ?? []) as $indicator) {
            if (!is_array($indicator)) {
                continue;
            }

            $current = (float)($indicator['cur_value'] ?? $indicator['current_val'] ?? 0);
            $limit = (float)($indicator['limit'] ?? $indicator['target_val'] ?? 0);
            if ($limit > 0 && $current >= $limit) {
                return true;
            }
        }

        foreach (['accumulative_check_points', 'check_points'] as $key) {
            $checkpoints = $progress[$key] ?? null;
            if (!is_array($checkpoints) || $checkpoints === []) {
                continue;
            }

            $allCompleted = true;
            foreach ($checkpoints as $checkpoint) {
                if (!is_array($checkpoint)) {
                    continue;
                }

                $checkpointStatus = (int)($checkpoint['status'] ?? 0);
                if ($checkpointStatus >= 2) {
                    continue;
                }

                $matched = false;
                foreach (($checkpoint['list'] ?? []) as $indicator) {
                    if (!is_array($indicator)) {
                        continue;
                    }

                    $current = (float)($indicator['cur_value'] ?? $indicator['current_val'] ?? 0);
                    $limit = (float)($indicator['limit'] ?? $indicator['target_val'] ?? 0);
                    if ($limit > 0 && $current >= $limit) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    $allCompleted = false;
                    break;
                }
            }

            if ($allCompleted) {
                return true;
            }
        }

        return false;
    }

    protected function isEraTaskReadyToClaim(EraActivityTask $task, ?array $progress): bool
    {
        if ($task->taskAwardType !== 1) {
            return false;
        }

        $taskStatus = (int)($progress['task_status'] ?? $task->taskStatus);
        return $taskStatus === 2;
    }

    protected function handleEraTaskReadyToClaim(array $item, EraActivityTask $task, ?array $progress): bool
    {
        if (!$this->isEraTaskReadyToClaim($task, $progress)) {
            return false;
        }

        $this->runEraClaimReward($item, $task);
        return true;
    }

    protected function runEraClaimReward(array $item, EraActivityTask $task): void
    {
        $label = $this->formatEraTaskLabel($item, $task);
        $infoResponse = ApiMission::info($task->taskId);
        if (($infoResponse['code'] ?? -1) !== 0 || !is_array($infoResponse['data'] ?? null)) {
            Log::warning("转盘活动: {$label} 获取领奖信息失败 Error: {$infoResponse['code']} -> {$infoResponse['message']}");
            $this->retryEraTask($item, $task, '领奖信息获取失败', 60);
            return;
        }

        $mission = $infoResponse['data'];
        $status = (int)($mission['status'] ?? 0);
        if ($status === 6) {
            $this->markEraTaskDone($item, $task);
            return;
        }

        if ($status === 1) {
            Notice::push(
                'activity_lottery',
                $this->formatCampaignNotice(
                    $this->campaignFromEraTask($item, $task),
                    '奖励领取需要额外账号绑定',
                    true
                )
            );
            $this->skipEraTask($item, $task, '奖励领取需要额外账号绑定');
            return;
        }

        $rewardInfo = is_array($mission['reward_info'] ?? null) ? $mission['reward_info'] : [];
        $rewardName = trim((string)($rewardInfo['award_name'] ?? $task->awardName));
        $receiveResponse = ApiMission::receive(
            $task->taskId,
            trim((string)($mission['act_id'] ?? '')),
            trim((string)($mission['act_name'] ?? '')),
            trim((string)($mission['task_name'] ?? $task->taskName)),
            $rewardName,
            ''
        );

        if (($receiveResponse['code'] ?? -1) !== 0) {
            if ((int)($receiveResponse['code'] ?? 0) === 202100) {
                Notice::push(
                    'activity_lottery',
                    $this->formatCampaignNotice(
                        $this->campaignFromEraTask($item, $task),
                        '奖励领取触发风控验证',
                        true
                    )
                );
                $this->skipEraTask($item, $task, '奖励领取触发风控验证');
                return;
            }

            Log::warning("转盘活动: {$label} 领奖失败 Error: {$receiveResponse['code']} -> {$receiveResponse['message']}");
            $this->retryEraTask($item, $task, '领奖失败', 60);
            return;
        }

        Log::notice("转盘活动: {$label} 领奖成功");
        $this->markEraTaskDone($item, $task);
    }

    protected function hasNumericTargetVideoId(EraActivityTask $task): bool
    {
        foreach ($task->targetVideoIds as $videoId) {
            if (ctype_digit((string)$videoId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentTopicArchive(array &$item, EraActivityTask $task): ?array
    {
        if ($task->topicId === '') {
            return null;
        }

        if (!isset($item['topic_archives']) || !is_array($item['topic_archives'])) {
            $preferredArchives = array_values(array_filter(
                $task->targetArchives,
                static fn (mixed $archive): bool => is_array($archive)
                    && (trim((string)($archive['aid'] ?? '')) !== '' || trim((string)($archive['bvid'] ?? '')) !== '')
            ));
            $fallbackArchives = $this->eraTopicArchiveService()->fetchArchives($task->topicId);
            $item['topic_archives'] = array_merge($preferredArchives, $fallbackArchives);
            $item['topic_archive_index'] = 0;
        }

        $archives = [];
        $seen = [];
        foreach ($item['topic_archives'] as $archive) {
            if (!is_array($archive)) {
                continue;
            }

            $aid = trim((string)($archive['aid'] ?? ''));
            $bvid = trim((string)($archive['bvid'] ?? ''));
            $key = $aid !== '' ? "aid:{$aid}" : ($bvid !== '' ? "bvid:{$bvid}" : '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $archives[] = $archive;
        }
        $item['topic_archives'] = $archives;

        $index = (int)($item['topic_archive_index'] ?? 0);
        if (!isset($archives[$index]) || !is_array($archives[$index])) {
            return null;
        }

        return $archives[$index];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fixedTaskArchives(EraActivityTask $task): array
    {
        $archives = [];
        foreach ($task->targetArchives as $archive) {
            if (!is_array($archive)) {
                continue;
            }

            if (trim((string)($archive['aid'] ?? '')) === '' && trim((string)($archive['bvid'] ?? '')) === '') {
                continue;
            }

            $archives[] = $archive;
        }

        if ($archives !== []) {
            return $archives;
        }

        foreach ($task->targetVideoIds as $videoId) {
            $videoId = trim((string)$videoId);
            if ($videoId === '') {
                continue;
            }

            $archives[] = [
                'aid' => ctype_digit($videoId) ? $videoId : '',
                'bvid' => str_starts_with(strtoupper($videoId), 'BV') ? $videoId : '',
            ];
        }

        return $archives;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentFixedArchive(array &$item, EraActivityTask $task): ?array
    {
        $archives = $this->fixedTaskArchives($task);
        if ($archives === []) {
            return null;
        }

        $index = (int)($item['fixed_archive_index'] ?? 0);
        if (!isset($archives[$index])) {
            $index = 0;
            $item['fixed_archive_index'] = 0;
        }

        return is_array($archives[$index]) ? $archives[$index] : null;
    }

    protected function advanceFixedArchive(array &$item, EraActivityTask $task): bool
    {
        $archives = $this->fixedTaskArchives($task);
        if ($archives === []) {
            return false;
        }

        $count = count($archives);
        $index = (int)($item['fixed_archive_index'] ?? 0);
        $item['fixed_archive_index'] = $count <= 1 ? 0 : (($index + 1) % $count);
        unset($item['watch_video_started'], $item['watch_video_archive'], $item['watch_video_session']);

        return true;
    }

    /**
     * @param array<string, mixed>|null $progress
     * @return int[]
     */
    protected function eraWatchThresholds(EraActivityTask $task, ?array $progress = null): array
    {
        $thresholds = [];
        $checkpointGroups = [];
        if (is_array($progress)) {
            foreach (['accumulative_check_points', 'check_points'] as $key) {
                if (is_array($progress[$key] ?? null)) {
                    $checkpointGroups[] = $progress[$key];
                }
            }
        }
        if ($checkpointGroups === []) {
            $checkpointGroups[] = $task->checkpoints;
        }

        foreach ($checkpointGroups as $checkpoints) {
            foreach ($checkpoints as $checkpoint) {
                if (!is_array($checkpoint)) {
                    continue;
                }

                foreach (($checkpoint['list'] ?? []) as $indicator) {
                    if (!is_array($indicator)) {
                        continue;
                    }

                    $limit = (int)($indicator['limit'] ?? $indicator['target_val'] ?? 0);
                    if ($limit > 0) {
                        $thresholds[] = $limit;
                    }
                }
            }
        }

        if ($thresholds === [] && $task->requiredWatchSeconds > 0) {
            $thresholds[] = $task->requiredWatchSeconds;
        }

        $thresholds = array_values(array_unique(array_filter($thresholds, static fn (int $limit): bool => $limit > 0)));
        sort($thresholds);

        return $thresholds;
    }

    protected function eraWatchCurrentSeconds(EraActivityTask $task, ?array $progress = null): int
    {
        $current = 0;

        if (is_array($progress)) {
            foreach (['accumulative_check_points', 'check_points'] as $key) {
                foreach (($progress[$key] ?? []) as $checkpoint) {
                    if (!is_array($checkpoint)) {
                        continue;
                    }

                    foreach (($checkpoint['list'] ?? []) as $indicator) {
                        if (!is_array($indicator)) {
                            continue;
                        }

                        $current = max($current, (int)($indicator['cur_value'] ?? $indicator['current_val'] ?? 0));
                    }
                }
            }

            foreach (($progress['indicators'] ?? []) as $indicator) {
                if (!is_array($indicator)) {
                    continue;
                }

                $current = max($current, (int)($indicator['cur_value'] ?? $indicator['current_val'] ?? 0));
            }
        }

        foreach ($task->checkpoints as $checkpoint) {
            if (!is_array($checkpoint)) {
                continue;
            }

            foreach (($checkpoint['list'] ?? []) as $indicator) {
                if (!is_array($indicator)) {
                    continue;
                }

                $current = max($current, (int)($indicator['cur_value'] ?? $indicator['current_val'] ?? 0));
            }
        }

        return max(0, $current);
    }

    protected function resolveEraNextWatchRemainingSeconds(EraActivityTask $task, ?array $progress = null, ?int $current = null): int
    {
        $thresholds = $this->eraWatchThresholds($task, $progress);
        if ($thresholds === []) {
            return 0;
        }

        $current ??= $this->eraWatchCurrentSeconds($task, $progress);
        foreach ($thresholds as $limit) {
            if ($current < $limit) {
                return max(0, $limit - $current);
            }
        }

        return 0;
    }

    protected function resolveEraNextWatchTargetSeconds(EraActivityTask $task, ?array $progress = null, ?int $current = null): int
    {
        $thresholds = $this->eraWatchThresholds($task, $progress);
        if ($thresholds === []) {
            return 0;
        }

        $current ??= $this->eraWatchCurrentSeconds($task, $progress);
        $limit = max($thresholds);
        $target = $limit + $this->eraWatchBufferSeconds($limit);

        return $current >= $target ? 0 : $target;
    }

    protected function resolveEraWatchWaitSeconds(EraActivityTask $task, array $archive, ?array $progress = null): int
    {
        $duration = max(15, max(1, (int)($archive['duration'] ?? 0)) - 1);
        $targetSeconds = $this->resolveEraNextWatchTargetSeconds($task, $progress);
        if ($targetSeconds > 0) {
            return max(15, min($duration, $targetSeconds));
        }

        return min($duration, 15);
    }

    protected function moveToNextTopicArchive(array &$item): bool
    {
        $archives = is_array($item['topic_archives'] ?? null) ? $item['topic_archives'] : [];
        $nextIndex = (int)($item['topic_archive_index'] ?? 0) + 1;
        if (!isset($archives[$nextIndex]) || !is_array($archives[$nextIndex])) {
            return false;
        }

        $item['topic_archive_index'] = $nextIndex;
        unset($item['watch_video_started'], $item['watch_video_archive'], $item['watch_video_session']);
        return true;
    }

    protected function runDueEraLiveTasks(): void
    {
        if ($this->config['wait_era_tasks'] === []) {
            return;
        }

        $queue = $this->config['wait_era_tasks'];
        $this->config['wait_era_tasks'] = [];

        foreach ($queue as $item) {
            if (!is_array($item)) {
                continue;
            }

            $task = EraActivityTask::fromArray(is_array($item['task'] ?? null) ? $item['task'] : []);
            $state = $this->getEraTaskState($item, $task);
            if ((bool)($state['completed'] ?? false)) {
                if ($this->processEraTaskCleanup($item, $task, $state)) {
                    continue;
                }

                $this->finalizeEraTaskDone($item);
                continue;
            }

            if ($this->handleEraTaskReadyToClaim($item, $task, null)) {
                continue;
            }

            if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE
                && !$this->isEraAutoCapabilityEnabled($task)
                && !$this->isEraTaskReadyToClaim($task, null)
            ) {
                Log::info("转盘活动: {$this->formatEraTaskLabel($item, $task)} 当前任务自动执行已关闭，已跳过");
                $this->markEraTaskDone($item, $task);
                continue;
            }

            $lane = $this->eraTaskLane($task, $state, $item);
            $laneBlockedUntil = $this->eraLaneBlockedUntil($lane);
            if ($laneBlockedUntil > time()) {
                $this->requeueEraTaskByLane($item, $task, $laneBlockedUntil);
                continue;
            }

            if ($task->capability === EraActivityTask::CAPABILITY_WATCH_LIVE) {
                $taskKey = (string)($item['key'] ?? '');
                $hasLiveSession = is_array($item['live_session'] ?? null);
                $activeLiveTaskKey = $this->activeEraLiveTaskKey();
                if (!$hasLiveSession && $activeLiveTaskKey !== '' && $activeLiveTaskKey !== $taskKey) {
                    $item['live_due_at'] = time() + 15;
                    $this->config['wait_era_tasks'][] = $item;
                    continue;
                }
            }

            if ($task->capability !== EraActivityTask::CAPABILITY_WATCH_LIVE || !$this->isEraLiveTaskDue($item)) {
                $this->config['wait_era_tasks'][] = $item;
                continue;
            }

            $this->reserveEraLane($lane);
            $this->runEraWatchLiveTask($item, $task);
        }
    }

    protected function isEraLiveTaskDue(array $item): bool
    {
        $dueAt = (int)($item['live_due_at'] ?? 0);
        return $dueAt <= 0 || $dueAt <= time();
    }

    /**
     * @param array<string, mixed> $session
     */
    protected function formatEraLiveRoomDetail(array $session): string
    {
        $roomId = (int)($session['room_id'] ?? 0);
        $uname = trim((string)($session['room_uname'] ?? ''));
        $title = trim((string)($session['room_title'] ?? ''));
        $pickSource = (string)($session['room_pick_source'] ?? 'room');

        $parts = [];
        if ($roomId > 0) {
            $parts[] = (string)$roomId;
        }
        if ($uname !== '') {
            $parts[] = $uname;
        }
        if ($title !== '') {
            $parts[] = $title;
        }
        if (str_starts_with($pickSource, 'area')) {
            $parts[] = '按分区匹配';
        }

        return implode(' / ', $parts);
    }

    protected function isEraTaskDue(array $item): bool
    {
        $dueAt = (int)($item['due_at'] ?? 0);
        return $dueAt <= 0 || $dueAt <= time();
    }

    protected function scheduleNextEraTick(): void
    {
        $nextDelay = $this->nextEraDueDelay();
        if ($nextDelay !== null) {
            $this->scheduleSoonerAfter((float)$nextDelay);
        }
    }

    protected function scheduleSoonerAfter(float $seconds, ?string $message = null): void
    {
        $seconds = max(0.0, $seconds);
        if ($this->taskResult !== null && $this->taskResult->nextRunAfterSeconds !== null && $this->taskResult->nextRunAfterSeconds <= $seconds) {
            return;
        }

        $this->scheduleAfter($seconds, $message);
    }

    protected function nextEraDueDelay(): ?int
    {
        $nextDelay = null;
        $now = time();
        foreach (['wait_era_tasks', 'pending_era_tasks'] as $queueKey) {
            foreach ($this->config[$queueKey] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $task = EraActivityTask::fromArray(is_array($item['task'] ?? null) ? $item['task'] : []);
                $dueAt = $this->eraTaskItemDueAt($item, $task);
                if ($dueAt <= 0) {
                    return 1;
                }

                $delay = max(1, $dueAt - $now);
                $nextDelay = $nextDelay === null ? $delay : min($nextDelay, $delay);
            }
        }

        return $nextDelay;
    }

    protected function isEraFollowResponseSuccessful(array $response): bool
    {
        $code = (int)($response['code'] ?? -1);
        $message = (string)($response['message'] ?? '');

        return $code === 0 || $code === 22014 || str_contains($message, '已关注');
    }

    protected function formatEraTaskLabel(array $item, EraActivityTask $task): string
    {
        $activityTitle = $this->campaignFromEraTask($item, $task)->title;
        if ($activityTitle === '') {
            return $task->taskName;
        }

        return "{$activityTitle} / {$task->taskName}";
    }

    protected function eraLiveWatchService(): EraLiveWatchService
    {
        return $this->eraLiveWatchService ??= new EraLiveWatchService();
    }

    protected function eraTopicArchiveService(): EraTopicArchiveService
    {
        return $this->eraTopicArchiveService ??= new EraTopicArchiveService();
    }

    protected function eraVideoWatchService(): EraVideoWatchService
    {
        return $this->eraVideoWatchService ??= new EraVideoWatchService();
    }

}
