<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\PolishMedal;

use Bhp\Api\XLive\AppUcenter\V1\ApiLikeInfoV3;
use Bhp\Api\XLive\FansMedal\V1\ApiMedalManage;
use Bhp\Cache\Cache;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

final class PolishMedalPlugin extends BasePlugin implements PluginTaskInterface
{
    private const MAX_LIGHT_QUEUE_PER_ROUND = 30;
    private const MIN_ACTION_DELAY_SECONDS = 30;
    private const MAX_ACTION_DELAY_SECONDS = 60;
    private const MIN_IDLE_DELAY_SECONDS = 10 * 60;
    private const MAX_IDLE_DELAY_SECONDS = 20 * 60;
    private const PANEL_PAGE_SIZE = 50;
    private const MAX_FETCH_PAGES = 20;
    private const MAX_FETCH_ITEMS = 1000;

    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiMedalManage $medalManageApi = null;
    private ?ApiLikeInfoV3 $likeInfoApi = null;
    private ?PolishMedalStateStore $stateStore = null;
    private ?PolishMedalWindow $window = null;
    private ?PolishMedalRoundPlanner $roundPlanner = null;

    /**
     * 初始化 PolishMedalPlugin
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
        if (!$this->enabled('polish_medal')) {
            return TaskResult::keepSchedule();
        }

        $now = $this->now();
        $state = $this->loadState();
        if (!$this->cleanupInvalidMedalEnabled() && $state->hasDeleteQueue()) {
            $state->setRound($state->roundRefreshedAt(), [], $state->roundLightQueue(), $state->roundStats());
            $this->saveState($state);
        }

        if (!$this->window()->contains($now)) {
            return $this->handleOutsideWindow($state, $now);
        }

        if (!$state->hasDeleteQueue() && !$state->hasLightQueue()) {
            $state = $this->refreshRoundState($state, $now);
            if (!$state->hasDeleteQueue() && !$state->hasLightQueue()) {
                $state->clearRound();
                $this->saveState($state);

                return TaskResult::after($this->randomInt(self::MIN_IDLE_DELAY_SECONDS, self::MAX_IDLE_DELAY_SECONDS));
            }
        }

        if ($this->cleanupInvalidMedalEnabled() && $state->hasDeleteQueue()) {
            $medal = $state->roundDeleteQueue()[0] ?? null;
            if (is_array($medal)) {
                if ($this->executeDelete($medal)) {
                    $state->popDeleteQueue();
                } else {
                    $failed = $state->popDeleteQueue();
                    if (is_array($failed)) {
                        $state->requeueDeleteQueue($failed);
                    }
                }
            }
            $this->saveState($state);

            return TaskResult::after($this->randomInt(self::MIN_ACTION_DELAY_SECONDS, self::MAX_ACTION_DELAY_SECONDS));
        }

        if ($state->hasLightQueue()) {
            $remainingBeforePop = count($state->roundLightQueue());
            $medal = $state->roundLightQueue()[0] ?? null;
            if (is_array($medal)) {
                if ($this->executeLight($medal, $remainingBeforePop)) {
                    $state->popLightQueue();
                } else {
                    $failed = $state->popLightQueue();
                    if (is_array($failed)) {
                        $state->requeueLightQueue($failed);
                    }
                }
            }
            $this->saveState($state);

            return TaskResult::after($this->randomInt(self::MIN_ACTION_DELAY_SECONDS, self::MAX_ACTION_DELAY_SECONDS));
        }

        $state->clearRound();
        $this->saveState($state);

        return TaskResult::after($this->randomInt(self::MIN_IDLE_DELAY_SECONDS, self::MAX_IDLE_DELAY_SECONDS));
    }

    /**
     * 获取当前时间
     * @return int
     */
    protected function now(): int
    {
        return time();
    }

    /**
     * 处理随机Int
     * @param int $min
     * @param int $max
     * @return int
     */
    protected function randomInt(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /**
     * 获取状态
     * @return PolishMedalRuntimeState
     */
    protected function loadState(): PolishMedalRuntimeState
    {
        return $this->stateStore()->load();
    }

    /**
     * 保存或更新状态
     * @param PolishMedalRuntimeState $state
     * @return void
     */
    protected function saveState(PolishMedalRuntimeState $state): void
    {
        $this->stateStore()->save($state);
    }

    /**
     * 刷新Round状态
     * @param PolishMedalRuntimeState $state
     * @param int $now
     * @return PolishMedalRuntimeState
     */
    protected function refreshRoundState(PolishMedalRuntimeState $state, int $now): PolishMedalRuntimeState
    {
        $medals = $this->fetchMedals();
        $planned = $this->roundPlanner()->plan($medals, $this->cleanupInvalidMedalEnabled());
        $state->setRound(
            $now,
            $planned['delete_queue'],
            $planned['light_queue'],
            $planned['stats'],
        );

        $stats = $state->roundStats();
        $this->info(sprintf(
            '点亮徽章: 刷新完成，总勋章 %d，已注销 %d，开播未点亮 %d，本轮删除 %d，本轮点亮 %d',
            $stats['total_medal_count'] ?? 0,
            $stats['logged_off_count'] ?? 0,
            $stats['live_unlit_count'] ?? 0,
            count($state->roundDeleteQueue()),
            count($state->roundLightQueue()),
        ));

        return $state;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchMedals(): array
    {
        $medalsById = [];
        $page = 1;
        $reportedTotal = 0;

        while ($page <= self::MAX_FETCH_PAGES && count($medalsById) < self::MAX_FETCH_ITEMS) {
            $response = $this->medalManageApi()->listPage($page, self::PANEL_PAGE_SIZE);
            $this->authFailureClassifier->assertNotAuthFailure($response, '点亮徽章: 获取勋章列表时账号未登录');

            if ((int)($response['code'] ?? -1) !== 0) {
                $this->warning(sprintf(
                    '点亮徽章: 获取勋章列表失败 page=%d -> %s',
                    $page,
                    (string)($response['message'] ?? '')
                ));
                break;
            }

            $reportedTotal = max($reportedTotal, (int)($response['total'] ?? 0));
            foreach ($response['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $medalId = (int)($item['medal_id'] ?? 0);
                if ($medalId <= 0) {
                    continue;
                }

                $medalsById[$medalId] = $item;
                if (count($medalsById) >= self::MAX_FETCH_ITEMS) {
                    break;
                }
            }

            if (!(bool)($response['has_more'] ?? false)) {
                break;
            }

            $nextPage = max($page + 1, (int)($response['next_page'] ?? ($page + 1)));
            if ($nextPage === $page) {
                break;
            }
            $page = $nextPage;
        }

        if ($reportedTotal > self::MAX_FETCH_ITEMS) {
            $this->warning(sprintf(
                '点亮徽章: 勋章总数 %d 超出处理上限 %d，仅处理前 %d 个',
                $reportedTotal,
                self::MAX_FETCH_ITEMS,
                self::MAX_FETCH_ITEMS,
            ));
        }

        return array_values($medalsById);
    }

    /**
     * @param array<string, mixed> $medal
     */
    protected function executeDelete(array $medal): bool
    {
        $medalId = (int)($medal['medal_id'] ?? 0);
        if ($medalId <= 0) {
            return true;
        }

        $response = $this->medalManageApi()->deleteMedal($medalId);
        $this->authFailureClassifier->assertNotAuthFailure($response, '清理徽章: 删除已注销勋章时账号未登录');

        $label = $this->medalLabel($medal);
        if ((int)($response['code'] ?? -1) === 0) {
            $existsInPanel = $this->medalExistsInPanel($medalId);
            if ($existsInPanel === true) {
                $this->warning(sprintf('清理徽章: 删除已注销勋章返回成功但列表仍存在 [%s]', $label));
                return false;
            }
            if ($existsInPanel === null) {
                $this->notice(sprintf('清理徽章: 删除已注销勋章成功 [%s]，但删后校验失败', $label));
                return false;
            }
            $this->notice(sprintf('清理徽章: 删除已注销勋章成功 [%s]', $label));
            return true;
        }

        $this->warning(sprintf(
            '清理徽章: 删除已注销勋章失败 [%s] CODE -> %s MSG -> %s',
            $label,
            (string)($response['code'] ?? ''),
            (string)($response['message'] ?? ''),
        ));

        return false;
    }

    /**
     * 处理medalExistsInPanel
     * @param int $medalId
     * @return ?bool
     */
    protected function medalExistsInPanel(int $medalId): ?bool
    {
        if ($medalId <= 0) {
            return false;
        }

        $page = 1;
        while ($page <= self::MAX_FETCH_PAGES) {
            $response = $this->medalManageApi()->listPage($page, self::PANEL_PAGE_SIZE);
            $this->authFailureClassifier->assertNotAuthFailure($response, '点亮徽章: 删除后校验勋章列表时账号未登录');

            if ((int)($response['code'] ?? -1) !== 0) {
                $this->warning(sprintf(
                    '点亮徽章: 删除后校验勋章列表失败 page=%d -> %s',
                    $page,
                    (string)($response['message'] ?? '')
                ));
                return null;
            }

            foreach ($response['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if ((int)($item['medal_id'] ?? 0) === $medalId) {
                    return true;
                }
            }

            if (!(bool)($response['has_more'] ?? false)) {
                return false;
            }

            $nextPage = max($page + 1, (int)($response['next_page'] ?? ($page + 1)));
            if ($nextPage === $page) {
                return false;
            }
            $page = $nextPage;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $medal
     */
    protected function executeLight(array $medal, int $remainingBeforePop): bool
    {
        $roomId = (int)($medal['room_id'] ?? 0);
        $targetId = (int)($medal['target_id'] ?? 0);
        if ($roomId <= 0 || $targetId <= 0) {
            return true;
        }

        $clickTime = $this->randomInt(30, 35);
        $response = $this->likeInfoApi()->likeReportV3($roomId, $targetId, $clickTime);
        $this->authFailureClassifier->assertNotAuthFailure($response, "点亮徽章: 在直播间@{$roomId}点赞时账号未登录");

        $label = $this->medalLabel($medal);
        $progress = $this->lightProgressLabel($remainingBeforePop);

        if ((int)($response['code'] ?? -1) === 0) {
            $this->notice(sprintf(
                '点亮徽章%s: 直播间@%d [%s] 点赞 %d 次成功',
                $progress,
                $roomId,
                $label,
                $clickTime,
            ));
            return true;
        }

        $this->warning(sprintf(
            '点亮徽章%s: 直播间@%d [%s] 点赞 %d 次失败 CODE -> %s MSG -> %s',
            $progress,
            $roomId,
            $label,
            $clickTime,
            (string)($response['code'] ?? ''),
            (string)($response['message'] ?? ''),
        ));

        return false;
    }

    /**
     * 处理handleOutside窗口
     * @param PolishMedalRuntimeState $state
     * @param int $now
     * @return TaskResult
     */
    protected function handleOutsideWindow(PolishMedalRuntimeState $state, int $now): TaskResult
    {
        $hadPendingRound = $state->roundRefreshedAt() > 0 || $state->hasDeleteQueue() || $state->hasLightQueue();
        if ($hadPendingRound) {
            $state->clearRound();
            $this->saveState($state);
            $this->info(sprintf(
                '点亮徽章: 当前不在运行窗口内，已丢弃未完成轮次，等待 %s 重新获取',
                $this->pluginWindowStartAt(),
            ));
        }

        return TaskResult::after($this->window()->secondsUntilNextStart($now));
    }

    /**
     * 处理medalManageAPI
     * @return ApiMedalManage
     */
    protected function medalManageApi(): ApiMedalManage
    {
        return $this->medalManageApi ??= new ApiMedalManage($this->appContext()->request());
    }

    /**
     * 处理like信息API
     * @return ApiLikeInfoV3
     */
    protected function likeInfoApi(): ApiLikeInfoV3
    {
        return $this->likeInfoApi ??= new ApiLikeInfoV3($this->appContext()->request());
    }

    /**
     * 处理状态存储
     * @return PolishMedalStateStore
     */
    protected function stateStore(): PolishMedalStateStore
    {
        return $this->stateStore ??= new PolishMedalStateStore($this->appContext()->cache());
    }

    /**
     * 处理窗口
     * @return PolishMedalWindow
     */
    protected function window(): PolishMedalWindow
    {
        return $this->window ??= new PolishMedalWindow($this->pluginWindowStartAt(), $this->pluginWindowEndAt());
    }

    /**
     * 处理roundPlanner
     * @return PolishMedalRoundPlanner
     */
    protected function roundPlanner(): PolishMedalRoundPlanner
    {
        return $this->roundPlanner ??= new PolishMedalRoundPlanner(self::MAX_LIGHT_QUEUE_PER_ROUND);
    }

    /**
     * 处理cleanupInvalidMedalEnabled
     * @return bool
     */
    protected function cleanupInvalidMedalEnabled(): bool
    {
        return $this->config('polish_medal.cleanup_invalid_medal', false, 'bool');
    }

    /**
     * @param array<string, mixed> $medal
     */
    private function medalLabel(array $medal): string
    {
        $level = max(0, (int)($medal['level'] ?? 0));
        $medalName = trim((string)($medal['medal_name'] ?? '未命名勋章'));

        return sprintf('Lv.%d %s', $level, $medalName);
    }

    /**
     * 处理lightProgressLabel
     * @param int $remainingBeforePop
     * @return string
     */
    private function lightProgressLabel(int $remainingBeforePop): string
    {
        if ($remainingBeforePop <= 0) {
            return '';
        }

        return sprintf('(本轮剩余 %d) ', $remainingBeforePop);
    }
}
