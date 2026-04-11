<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LiveReservation;

use Bhp\ArticleSource\SpaceArticleSourceService;
use Bhp\Api\Space\ApiReservation;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

final class LiveReservationPlugin extends BasePlugin implements PluginTaskInterface
{
    private const START_RANDOM_DELAY_MIN_MINUTES = 15;
    private const START_RANDOM_DELAY_MAX_MINUTES = 45;
    private const DELAY_AFTER_FETCH_WITH_TASKS_MIN = 180;
    private const DELAY_AFTER_FETCH_WITH_TASKS_MAX = 480;
    private const DELAY_AFTER_FETCH_EMPTY_MIN = 480;
    private const DELAY_AFTER_FETCH_EMPTY_MAX = 1200;
    private const DELAY_AFTER_RESERVE_MIN = 180;
    private const DELAY_AFTER_RESERVE_MAX = 480;

    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiReservation $reservationApi = null;
    private ?SpaceArticleSourceService $articleSourceService = null;
    private ?LiveReservationStateStore $stateStore = null;
    private ?LiveReservationWindow $window = null;

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('live_reservation')) {
            return TaskResult::keepSchedule();
        }

        $now = $this->now();
        $state = LiveReservationRuntimeState::bootstrap($this->stateStore()->load());
        $state->resetForBizDate($this->bizDate($now));

        if (!$this->window()->contains($now)) {
            $this->stateStore()->save($state->all());

            return $this->nextPluginStartTaskResult(
                self::START_RANDOM_DELAY_MIN_MINUTES,
                self::START_RANDOM_DELAY_MAX_MINUTES,
            );
        }

        if (!$state->sourceSynced()) {
            $snapshot = $this->articleSource()->snapshotForToday();
            $remoteUpMids = $snapshot->liveReservationUpMids;
            $configuredUpMids = $this->parseConfiguredVmids((string)$this->config('live_reservation.vmids', ''));
            $mergedUpMids = array_values(array_unique(array_merge($remoteUpMids, $configuredUpMids)));
            $this->info(sprintf(
                '预约直播: 获取数据成功，远程源 %d，本地源 %d，总 %d',
                count($remoteUpMids),
                count($configuredUpMids),
                count($mergedUpMids),
            ));
            $state->seedUpMidQueue(
                $snapshot->reservationSourceCvId,
                $remoteUpMids,
                $configuredUpMids,
            );
        }

        if ($state->pendingReservationCount() > 0) {
            $result = $this->executeReservationTask($state);
            $this->stateStore()->save($state->all());

            return $result;
        }

        if ($state->pendingUpMidCount() > 0) {
            $result = $this->discoverReservationTasks($state);
            $this->stateStore()->save($state->all());

            return $result;
        }

        $this->stateStore()->save($state->all());

        if (!$state->hasWork()) {
            return $this->nextPluginStartTaskResult(
                self::START_RANDOM_DELAY_MIN_MINUTES,
                self::START_RANDOM_DELAY_MAX_MINUTES,
                true,
            );
        }

        return TaskResult::after(mt_rand(1, 3) * 60 * 60);
    }

    protected function discoverReservationTasks(LiveReservationRuntimeState $state): TaskResult
    {
        $upMid = $state->shiftPendingUpMid();
        if ($upMid === null) {
            return $this->nextPluginStartTaskResult(
                self::START_RANDOM_DELAY_MIN_MINUTES,
                self::START_RANDOM_DELAY_MAX_MINUTES,
                true,
            );
        }

        $reservationList = $this->fetchReservation($upMid);
        $added = $state->enqueueReservations($reservationList);
        $parentCurrent = $state->processedUpMidCount();
        $parentTotal = max(1, $state->totalUpMidCount());
        if ($added > 0) {
            $state->beginReservationBatch($upMid, $added);
            $this->info(sprintf(
                '预约直播: 父任务进度 (%d/%d) 子任务进度 (0/%d) UP主 %s',
                $parentCurrent,
                $parentTotal,
                $added,
                $upMid,
            ));
        } else {
            $state->clearReservationBatch();
            $this->info(sprintf(
                '预约直播: 父任务进度 (%d/%d) 子任务进度 (0/0) UP主 %s 当前UP没有可预约的直播',
                $parentCurrent,
                $parentTotal,
                $upMid,
            ));
        }

        if ($state->pendingReservationCount() > 0) {
            return TaskResult::after($this->randomDelay(self::DELAY_AFTER_FETCH_WITH_TASKS_MIN, self::DELAY_AFTER_FETCH_WITH_TASKS_MAX));
        }

        if ($state->pendingUpMidCount() > 0) {
            return TaskResult::after($this->randomDelay(self::DELAY_AFTER_FETCH_EMPTY_MIN, self::DELAY_AFTER_FETCH_EMPTY_MAX));
        }

        return $this->nextPluginStartTaskResult(
            self::START_RANDOM_DELAY_MIN_MINUTES,
            self::START_RANDOM_DELAY_MAX_MINUTES,
            true,
        );
    }

    protected function executeReservationTask(LiveReservationRuntimeState $state): TaskResult
    {
        $reservation = $state->shiftPendingReservation();
        if (!is_array($reservation)) {
            $state->clearReservationBatch();
            return $state->pendingUpMidCount() > 0
                ? TaskResult::after($this->randomDelay(self::DELAY_AFTER_FETCH_EMPTY_MIN, self::DELAY_AFTER_FETCH_EMPTY_MAX))
                : $this->nextPluginStartTaskResult(
                    self::START_RANDOM_DELAY_MIN_MINUTES,
                    self::START_RANDOM_DELAY_MAX_MINUTES,
                    true,
                );
        }

        $currentBatchUpMid = $state->currentBatchUpMid() ?? trim((string)($reservation['vmid'] ?? ''));
        if ($state->currentBatchReservationTotal() <= 0) {
            $state->beginReservationBatch($currentBatchUpMid, max(1, $state->pendingReservationCount() + 1));
        }

        $current = $state->currentBatchProcessedReservationCount() + 1;
        $total = max($current, $state->currentBatchReservationTotal());
        $this->info(sprintf(
            '预约直播: 父任务进度 (%d/%d) 子任务进度 (%d/%d) UP主 %s',
            $state->processedUpMidCount(),
            max(1, $state->totalUpMidCount()),
            $current,
            $total,
            $currentBatchUpMid,
        ));
        $this->reserve($reservation);
        $state->incrementProcessedReservationCount();
        $state->incrementCurrentBatchProcessedReservationCount();

        if ($state->currentBatchProcessedReservationCount() >= $state->currentBatchReservationTotal()) {
            $state->clearReservationBatch();
        }

        if ($state->pendingReservationCount() > 0) {
            return TaskResult::after($this->randomDelay(self::DELAY_AFTER_RESERVE_MIN, self::DELAY_AFTER_RESERVE_MAX));
        }

        if ($state->pendingUpMidCount() > 0) {
            return TaskResult::after($this->randomDelay(self::DELAY_AFTER_FETCH_EMPTY_MIN, self::DELAY_AFTER_FETCH_EMPTY_MAX));
        }

        return $this->nextPluginStartTaskResult(
            self::START_RANDOM_DELAY_MIN_MINUTES,
            self::START_RANDOM_DELAY_MAX_MINUTES,
            true,
        );
    }

    /**
     * @return string[]
     */
    protected function parseConfiguredVmids(string $vmids): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            preg_split('/[|,]/', $vmids, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @return array<int, array{sid: mixed, name: mixed, vmid: mixed, jump_url: mixed, text: mixed}>
     */
    protected function fetchReservation(string $vmid): array
    {
        $reservationList = [];
        $response = $this->reservationApi()->reservation($vmid);
        $this->authFailureClassifier->assertNotAuthFailure($response, '预约直播: 获取预约列表时账号未登录');
        if ($response['code']) {
            $this->warning("预约直播: 获取预约列表失败: {$response['code']} -> {$response['message']}");
        } else {
            $deData = $response['data'] ?: [];
            foreach ($deData as $data) {
                $result = $this->checkLottery($data);
                if (!$result) {
                    continue;
                }
                $reservationList[] = $result;
            }
        }

        return $reservationList;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{sid: mixed, name: mixed, vmid: mixed, jump_url: mixed, text: mixed}|false
     */
    protected function checkLottery(array $data): bool|array
    {
        if (($data['etime'] ?? 0) <= time()) {
            return false;
        }
        if (isset($data['reserve_record_ctime']) && (int)$data['reserve_record_ctime'] > 0) {
            return false;
        }
        if (($data['is_follow'] ?? false)) {
            return false;
        }
        if (array_key_exists('lottery_prize_info', $data) && array_key_exists('lottery_type', $data)) {
            return [
                'sid' => $data['sid'],
                'name' => $data['name'],
                'vmid' => $data['up_mid'],
                'jump_url' => $data['lottery_prize_info']['jump_url'],
                'text' => $data['lottery_prize_info']['text'],
            ];
        }

        return false;
    }

    /**
     * @param array{sid: mixed, name: mixed, vmid: mixed, jump_url: mixed, text: mixed} $data
     */
    protected function reserve(array $data): void
    {
        $response = $this->reservationApi()->reserve((int)$data['sid'], (int)$data['vmid']);
        $this->authFailureClassifier->assertNotAuthFailure($response, '预约直播: 执行预约时账号未登录');

        $this->info("预约直播: {$data['name']}|{$data['vmid']}|{$data['sid']}");
        $this->info("预约直播: {$data['text']}");
        $this->info("预约直播: {$data['jump_url']}");

        if ($response['code']) {
            $this->warning("预约直播: 尝试预约并抽奖失败 {$response['code']} -> {$response['message']}");
        } else {
            $this->notice("预约直播: 尝试预约并抽奖成功 {$response['message']}");
        }
    }

    protected function articleSource(): SpaceArticleSourceService
    {
        return $this->articleSourceService ??= new SpaceArticleSourceService($this->appContext());
    }

    protected function stateStore(): LiveReservationStateStore
    {
        return $this->stateStore ??= new LiveReservationStateStore($this->cache());
    }

    protected function reservationApi(): ApiReservation
    {
        return $this->reservationApi ??= new ApiReservation($this->appContext()->request());
    }

    protected function window(): LiveReservationWindow
    {
        return $this->window ??= new LiveReservationWindow($this->pluginWindowStartAt(), $this->pluginWindowEndAt());
    }

    protected function now(): int
    {
        return time();
    }

    protected function bizDate(int $timestamp): string
    {
        return date('Y-m-d', $timestamp);
    }

    protected function randomDelay(int $minSeconds, int $maxSeconds): int
    {
        return mt_rand($minSeconds, $maxSeconds);
    }
}
