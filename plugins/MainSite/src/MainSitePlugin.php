<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\MainSite;

use Bhp\Api\Api\X\Player\ApiPlayer;
use Bhp\Api\DynamicSvr\ApiDynamicSvr;
use Bhp\Api\Video\ApiCoin;
use Bhp\Api\Video\ApiShare;
use Bhp\Api\Video\ApiVideo;
use Bhp\Api\Video\ApiWatch;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\NoLoginException;

class MainSitePlugin extends BasePlugin implements PluginTaskInterface
{
    protected const MAX_NEWLIST_ATTEMPTS = 3;
    protected const COIN_FETCH_REDUNDANCY_MULTIPLIER = 3;
    protected const COIN_FETCH_MIN_SIZE = 10;
    protected const COIN_FETCH_MAX_SIZE = 30;
    protected const COIN_ALREADY_LIMIT_CODE = 34005;

    protected ?MainSiteRuntimeState $state = null;

    protected ?MainSiteRecordStore $recordStore = null;

    protected ?MainSiteArchiveService $archiveService = null;
    protected ?ApiVideo $videoApi = null;
    protected ?ApiCoin $coinApi = null;
    protected ?ApiShare $shareApi = null;
    protected ?ApiWatch $watchApi = null;
    protected ?AuthFailureClassifier $authFailureClassifier = null;

    /**
     * @var array<string, int|string>
     */

    /**
     * 初始化 MainSitePlugin
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    /**
     * 执行一次任务
     * @return TaskResult
     */
    public function runOnce(): TaskResult
    {
        if (!$this->enabled('main_site')) {
            return TaskResult::keepSchedule();
        }

        $this->resetTaskResult();
        $this->state = MainSiteRuntimeState::bootstrap(
            $this->recordStore()->load(),
            $this->recordStore()->defaults(),
        );

        try {
            $success = $this->watchTask() && $this->shareTask() && $this->coinTask();
        } finally {
            $this->persistState();
        }

        return $this->resolveTaskResult(
            $success ? TaskResult::nextDayAt(10, 0, 0, 1, 60) : TaskResult::after(mt_rand(60, 180) * 60)
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function initRecords(): array
    {
        return [
            'watch' => [],
            'share' => [],
            'coin' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchCustomArchives(int $num = 30): array
    {
        return $this->archiveService()->fetchPreferredArchives(
            (string) $this->config('main_site.fetch_aids_mode', 'random'),
            $num
        );
    }

    /**
     * @throws NoLoginException
     */
    protected function coinTask(string $key = 'coin'): bool
    {
        if (!$this->config('main_site.add_coin', false, 'bool')) {
            return true;
        }

        return $this->runCoinTaskFlow($key);

        $state = $this->state();
        if ($this->config('main_site.when_lv6_stop_coin', false, 'bool')) {
            $userInfo = $this->userProfiles()->navInfo();
            if ($userInfo->level_info->current_level >= 6) {
                $this->notice('主站任务: 已满6级, 停止投币');

                return true;
            }
        }

        if ($state->hasMarker($key, $this->getKey())) {
            return true;
        }

        $pendingCoins = $state->pendingCoins();
        if ($pendingCoins === []) {
            $estimateNum = $this->config('main_site.add_coin_num', 0, 'int');
            $stockNum = $this->getCoinStock();
            $alreadyNum = $this->getCoinAlready();
            $actualNum = intval(min($estimateNum, $stockNum)) - $alreadyNum;

            $this->info("主站任务: 硬币库存 $stockNum 预投 $estimateNum 已投 $alreadyNum 还需投币 $actualNum");
            if ($actualNum <= 0) {
                $this->notice('主站任务: 今日投币上限已满');
                $state->markCompleted($key, $this->getKey());

                return true;
            }

            $aids = $this->fetchCustomArchives($actualNum);
            $aids = array_map('strval', array_column($aids, 'aid'));

            $this->info('主站任务: 预投币稿件 ' . implode(' ', $aids));
            $state->setPendingCoins($aids);
            $pendingCoins = $state->pendingCoins();
        }

        if ($pendingCoins === []) {
            return false;
        }

        $aid = $pendingCoins[0];
        if (!$this->reward((string) $aid)) {
            $this->scheduleAfter(60.0);

            return false;
        }

        array_shift($pendingCoins);
        if ($pendingCoins !== []) {
            $state->setPendingCoins($pendingCoins);
            $this->scheduleAfter(1.0);

            return false;
        }

        $state->clearPendingCoins();
        $state->markCompleted($key, $this->getKey());

        return true;
    }

    /**
     * @throws NoLoginException
     */
    protected function reward(string $aid): bool
    {
        $response = $this->coinApi()->appCoin($aid);
        $code = (int)($response['code'] ?? -1);
        $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
        switch ($code) {
            case -101:
                throw new NoLoginException($message);
            case 0:
                $this->notice("主站任务: $aid 投币成功");

                return true;
            default:
                $this->warning("主站任务: $aid 投币失败 {$code} -> {$message}");

                return false;
        }
    }

    /**
     * 获取CoinAlready
     * @return int
     */
    /**
     * @throws NoLoginException
     */
    protected function runCoinTaskFlow(string $key): bool
    {
        $taskKey = $this->getKey();
        $state = $this->state();
        $state->syncCoinSession($taskKey);

        if ($this->config('main_site.when_lv6_stop_coin', false, 'bool')) {
            $userInfo = $this->userProfiles()->navInfo();
            if ($userInfo->level_info->current_level >= 6) {
                $this->notice('涓荤珯浠诲姟: 宸叉弧6绾? 鍋滄鎶曞竵');

                return true;
            }
        }

        if ($state->hasMarker($key, $taskKey)) {
            return true;
        }

        $remaining = $state->coinSessionRemaining($taskKey);
        if ($remaining === null) {
            $remaining = $this->initializeCoinSession($key, $taskKey);
            if ($remaining <= 0) {
                return true;
            }
        }

        $pendingCoins = $state->pendingCoins();
        if ($pendingCoins === []) {
            $pendingCoins = $this->buildCoinPendingQueue($remaining, $taskKey);
            if ($pendingCoins === []) {
                $this->scheduleAfter(60.0);

                return false;
            }

            $state->setPendingCoins($pendingCoins);
        }

        $aid = $pendingCoins[0] ?? null;
        if (!is_string($aid)) {
            $this->scheduleAfter(60.0);

            return false;
        }

        $code = $this->rewardCode($aid);
        if ($code === self::COIN_ALREADY_LIMIT_CODE) {
            $state->addRejectedCoin($taskKey, $aid);
            array_shift($pendingCoins);
            $this->notice("涓荤珯浠诲姟: $aid 宸茶揪鎶曞竵涓婇檺锛岃烦杩囧綋鍓嶇浠?");

            return $this->advanceCoinQueue($key, $taskKey, $pendingCoins, $remaining);
        }

        if ($code !== 0) {
            $state->setPendingCoins($pendingCoins);
            $this->scheduleAfter(60.0);

            return false;
        }

        $remaining = $state->decreaseCoinSessionRemaining($taskKey);
        array_shift($pendingCoins);

        return $this->advanceCoinQueue($key, $taskKey, $pendingCoins, $remaining);
    }

    protected function initializeCoinSession(string $key, string $taskKey): int
    {
        $remaining = $this->resolveCoinRemaining();
        $state = $this->state();
        $state->startCoinSession($taskKey, $remaining);
        if ($remaining > 0) {
            return $remaining;
        }

        $this->notice('涓荤珯浠诲姟: 浠婃棩鎶曞竵涓婇檺宸叉弧');
        $state->markCompleted($key, $taskKey);
        $state->clearCoinSession($taskKey);

        return 0;
    }

    protected function resolveCoinRemaining(): int
    {
        $estimateNum = $this->config('main_site.add_coin_num', 0, 'int');
        $stockNum = $this->getCoinStock();
        $alreadyNum = $this->getCoinAlready();
        $actualNum = intval(min($estimateNum, $stockNum)) - $alreadyNum;

        $this->info("涓荤珯浠诲姟: 纭竵搴撳瓨 $stockNum 棰勬姇 $estimateNum 宸叉姇 $alreadyNum 杩橀渶鎶曞竵 $actualNum");

        return max(0, $actualNum);
    }

    /**
     * @return string[]
     */
    protected function buildCoinPendingQueue(int $remaining, string $taskKey): array
    {
        $archives = $this->fetchCustomArchives($this->resolveCoinFetchSize($remaining));
        $rejectedCoins = $this->state()->rejectedCoins($taskKey);
        $aids = [];

        foreach ($archives as $archive) {
            if (!is_array($archive)) {
                continue;
            }

            $aid = $this->extractArchiveAid($archive);
            if ($aid === '' || in_array($aid, $rejectedCoins, true) || in_array($aid, $aids, true)) {
                continue;
            }

            $aids[] = $aid;
        }

        if ($aids !== []) {
            $this->info('涓荤珯浠诲姟: 棰勬姇甯佺浠?' . implode(' ', $aids));
        }

        return $aids;
    }

    protected function resolveCoinFetchSize(int $remaining): int
    {
        return min(
            self::COIN_FETCH_MAX_SIZE,
            max(self::COIN_FETCH_MIN_SIZE, $remaining * self::COIN_FETCH_REDUNDANCY_MULTIPLIER)
        );
    }

    /**
     * @param string[] $pendingCoins
     */
    protected function advanceCoinQueue(string $key, string $taskKey, array $pendingCoins, int $remaining): bool
    {
        $state = $this->state();
        if ($remaining <= 0) {
            $state->markCompleted($key, $taskKey);
            $state->clearCoinSession($taskKey);

            return true;
        }

        if ($pendingCoins !== []) {
            $state->setPendingCoins($pendingCoins);
            $this->scheduleAfter(1.0);

            return false;
        }

        $state->clearPendingCoins();
        $pendingCoins = $this->buildCoinPendingQueue($remaining, $taskKey);
        if ($pendingCoins === []) {
            $this->scheduleAfter(60.0);

            return false;
        }

        $state->setPendingCoins($pendingCoins);
        $this->scheduleAfter(1.0);

        return false;
    }

    /**
     * @throws NoLoginException
     */
    protected function rewardCode(string $aid): int
    {
        $response = $this->coinApi()->appCoin($aid);
        $code = (int)($response['code'] ?? -1);
        $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
        switch ($code) {
            case -101:
                throw new NoLoginException($message);
            case 0:
                $this->notice("涓荤珯浠诲姟: $aid 鎶曞竵鎴愬姛");

                return 0;
            default:
                $this->warning("涓荤珯浠诲姟: $aid 鎶曞竵澶辫触 {$code} -> {$message}");

                return $code;
        }
    }

    protected function getCoinAlready(): int
    {
        $response = $this->coinApi()->addLog();
        $this->assertNotAuthFailure($response, '主站任务: 获取已投硬币时账号未登录');
        if (($response['code'] ?? 0) !== 0 || !isset($response['data']['list']) || !is_array($response['data']['list'])) {
            $code = $response['code'] ?? 'unknown';
            $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
            $this->warning("主站任务: 获取已投硬币失败 {$code} -> {$message}");

            return 0;
        }

        $logs = $response['data']['list'];
        $coins = 0;
        $today = date('Y-m-d');
        foreach ($logs as $log) {
            if (!is_array($log)) {
                continue;
            }

            $time = $log['time'] ?? null;
            if (!is_string($time)) {
                continue;
            }

            $logTimestamp = strtotime($time);
            if ($logTimestamp === false) {
                continue;
            }

            if (date('Y-m-d', $logTimestamp) !== $today) {
                break;
            }

            $coins += $this->countCoinDeltaForLog($log, $today);
        }

        return $coins;
    }

    /**
     * 获取CoinStock
     * @return int
     */
    protected function getCoinStock(): int
    {
        $response = $this->coinApi()->getCoin();
        $this->assertNotAuthFailure($response, '主站任务: 获取硬币库存时账号未登录');
        if (($response['code'] ?? 0) !== 0 || !isset($response['data']['money'])) {
            $this->warning('主站任务: 获取硬币库存失败或者硬币为null ' . json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 0;
        }

        return (int) $response['data']['money'];
    }

    /**
     * @throws NoLoginException
     */
    protected function shareTask(string $key = 'share'): bool
    {
        if (!$this->config('main_site.share', false, 'bool')) {
            return true;
        }

        if ($this->state()->hasMarker($key, $this->getKey())) {
            return true;
        }

        $archives = $this->fetchCustomArchives(10);
        $archive = $this->archiveService()->pickLastArchive($archives);
        $aid = $this->extractArchiveAid($archive);
        if ($aid === '') {
            return false;
        }

        $response = $this->shareApi()->share($aid);
        $code = (int)($response['code'] ?? -1);
        $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
        switch ($code) {
            case -101:
                throw new NoLoginException($message);
            case 0:
                $this->notice("主站任务: $aid 分享成功");
                $this->state()->markCompleted($key, $this->getKey());

                return true;
            default:
                $this->warning("主站任务: $aid 分享失败 {$code} -> {$message}，稍后将重试");

                return false;
        }
    }

    /**
     * 处理观看任务
     * @param string $key
     * @return bool
     */
    protected function watchTask(string $key = 'watch'): bool
    {
        if (!$this->config('main_site.watch', false, 'bool')) {
            return true;
        }

        $state = $this->state();
        if ($state->hasMarker($key, $this->getKey())) {
            return true;
        }

        $pendingWatch = $state->pendingWatch();
        if ($pendingWatch !== null) {
            return $this->finishPendingWatch($pendingWatch, $key);
        }

        $archives = $this->fetchCustomArchives(10);
        $archive = $this->archiveService()->pickLastArchive($archives);
        $aid = $this->extractArchiveAid($archive);
        if ($aid === '') {
            return false;
        }

        if (isset($archive['duration']) && is_int($archive['duration'])) {
            $info = $archive;
        } else {
            $info = $this->getArchiveInfo($aid);
            if (empty($info)) {
                return false;
            }
        }

        $aid = $this->extractArchiveAid($info);
        if ($aid === '') {
            return false;
        }

        $cid = (string) ($info['cid'] ?? '');
        $duration = (int) ($info['duration'] ?? 0);
        if ($cid === '' || $duration <= 0) {
            return false;
        }

        $response = $this->watchApi()->video($aid, $cid);
        $this->assertNotAuthFailure($response, "主站任务: {$aid} 开始观看时账号未登录");
        $code = (int)($response['code'] ?? -1);
        $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
        if ($code !== 0) {
            $this->warning("主站任务: $aid 观看失败 {$code} -> {$message}");

            return false;
        }

        $response = $this->watchApi()->heartbeat($aid, $cid, $duration);
        $this->assertNotAuthFailure($response, "主站任务: {$aid} 发送观看心跳时账号未登录");
        $code = (int)($response['code'] ?? -1);
        $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
        if ($code !== 0) {
            $this->warning("主站任务: $aid 观看失败 {$code} -> {$message}");

            return false;
        }

        $state->setPendingWatch([
            'aid' => $aid,
            'cid' => $cid,
            'duration' => $duration,
        ]);
        $this->scheduleAfter(5.0);

        return false;
    }

    /**
     * @param array<string, int|string> $pendingWatch
     */
    protected function finishPendingWatch(array $pendingWatch, string $key): bool
    {
        $aid = (string) ($pendingWatch['aid'] ?? '');
        $cid = (string) ($pendingWatch['cid'] ?? '');
        $duration = (int) ($pendingWatch['duration'] ?? 0);
        if ($aid === '' || $cid === '' || $duration <= 0) {
            $this->state()->clearPendingWatch();

            return false;
        }

        $data = [];
        $data['played_time'] = $duration - 1;
        $data['play_type'] = 0;
        $data['start_ts'] = time();

        $response = $this->watchApi()->heartbeat($aid, $cid, $duration, '', $data);
        $this->assertNotAuthFailure($response, "主站任务: {$aid} 完成观看时账号未登录");
        $code = (int)($response['code'] ?? -1);
        $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';
        if ($code !== 0) {
            $this->warning("主站任务: $aid 观看失败 {$code} -> {$message}");
            $this->scheduleAfter(60.0);

            return false;
        }

        $this->notice("主站任务: $aid 观看成功");
        $this->state()->clearPendingWatch();
        $this->state()->markCompleted($key, $this->getKey());

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getArchiveInfo(string $aid): array
    {
        return $this->archiveService()->fetchArchiveInfo($aid);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRandomAvInfos(): array
    {
        for ($attempt = 0; $attempt < self::MAX_NEWLIST_ATTEMPTS; $attempt++) {
            $info = $this->extractNewlistArchives($this->fetchVideoNewlist(mt_rand(1, 1000), 10));
            if ($info === []) {
                continue;
            }

            shuffle($info);

            return $info;
        }

        return [];
    }

    /**
     * 获取键
     * @return string
     */
    protected function getKey(): string
    {
        return substr(md5(md5(date('Y-m-d', time()))), 8, 8);
    }

    /**
     * 处理状态
     * @return MainSiteRuntimeState
     */
    protected function state(): MainSiteRuntimeState
    {
        if ($this->state === null) {
            $this->state = MainSiteRuntimeState::bootstrap(
                $this->recordStore()->load(),
                $this->recordStore()->defaults(),
            );
        }

        return $this->state;
    }

    /**
     * 保存或更新状态
     * @return void
     */
    protected function persistState(): void
    {
        if ($this->state !== null) {
            $this->recordStore()->save($this->state->all());
        }
    }

    /**
     * 记录存储
     * @return MainSiteRecordStore
     */
    protected function recordStore(): MainSiteRecordStore
    {
        return $this->recordStore ??= new MainSiteRecordStore($this->cache());
    }

    /**
     * 处理archive服务
     * @return MainSiteArchiveService
     */
    protected function archiveService(): MainSiteArchiveService
    {
        $request = $this->appContext()->request();

        return $this->archiveService ??= new MainSiteArchiveService(
            $this->appContext()->log(),
            $this->videoApi(),
            new ApiDynamicSvr($request),
            new ApiPlayer($request),
        );
    }

    /**
     * @param array<string, mixed> $archive
     */
    protected function extractArchiveAid(array $archive): string
    {
        $aid = $archive['aid'] ?? null;

        return is_int($aid) || is_string($aid) ? (string) $aid : '';
    }

    /**
     * 处理数量CoinDeltaFor日志
     * @param mixed $log
     * @param string $today
     * @return int
     */
    protected function countCoinDeltaForLog(mixed $log, string $today): int
    {
        if (!is_array($log)) {
            return 0;
        }

        $time = $log['time'] ?? null;
        $reason = $log['reason'] ?? null;
        $delta = $log['delta'] ?? null;
        if (!is_string($time) || !is_string($reason) || !is_int($delta)) {
            return 0;
        }

        $timestamp = strtotime($time);
        if ($timestamp === false || date('Y-m-d', $timestamp) !== $today) {
            return 0;
        }

        if (!str_contains($reason, '投币') && !str_contains($reason, '打赏')) {
            return 0;
        }

        return match ($delta) {
            -1 => 1,
            -2 => 2,
            default => 0,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchVideoNewlist(int $pageNumber, int $pageSize): array
    {
        return $this->videoApi()->newlist($pageNumber, $pageSize);
    }

    /**
     * 处理视频API
     * @return ApiVideo
     */
    protected function videoApi(): ApiVideo
    {
        return $this->videoApi ??= new ApiVideo($this->appContext()->request());
    }

    /**
     * 处理coinAPI
     * @return ApiCoin
     */
    protected function coinApi(): ApiCoin
    {
        return $this->coinApi ??= new ApiCoin($this->appContext()->request());
    }

    /**
     * 处理shareAPI
     * @return ApiShare
     */
    protected function shareApi(): ApiShare
    {
        return $this->shareApi ??= new ApiShare($this->appContext()->request());
    }

    /**
     * 处理观看API
     * @return ApiWatch
     */
    protected function watchApi(): ApiWatch
    {
        return $this->watchApi ??= new ApiWatch($this->appContext()->request());
    }

    /**
     * @throws NoLoginException
     */
    protected function assertNotAuthFailure(array $response, string $fallbackMessage): void
    {
        $this->authFailureClassifier ??= new AuthFailureClassifier();
        $this->authFailureClassifier->assertNotAuthFailure($response, $fallbackMessage);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractNewlistArchives(mixed $response): array
    {
        if (!is_array($response) || (($response['code'] ?? 0) !== 0)) {
            return [];
        }

        $archives = $response['data']['archives'] ?? null;
        if (!is_array($archives)) {
            return [];
        }

        return array_values(array_filter($archives, static fn (mixed $archive): bool => is_array($archive)));
    }
}
