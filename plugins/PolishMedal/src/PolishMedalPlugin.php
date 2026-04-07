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
    private const WINDOW_START = '08:00:00';
    private const WINDOW_END = '02:00:00';
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

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

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
            $medal = $state->popDeleteQueue();
            $this->saveState($state);
            if (is_array($medal)) {
                $this->executeDelete($medal);
            }

            return TaskResult::after($this->randomInt(self::MIN_ACTION_DELAY_SECONDS, self::MAX_ACTION_DELAY_SECONDS));
        }

        if ($state->hasLightQueue()) {
            $remainingBeforePop = count($state->roundLightQueue());
            $medal = $state->popLightQueue();
            $this->saveState($state);
            if (is_array($medal)) {
                $this->executeLight($medal, $remainingBeforePop);
            }

            return TaskResult::after($this->randomInt(self::MIN_ACTION_DELAY_SECONDS, self::MAX_ACTION_DELAY_SECONDS));
        }

        $state->clearRound();
        $this->saveState($state);

        return TaskResult::after($this->randomInt(self::MIN_IDLE_DELAY_SECONDS, self::MAX_IDLE_DELAY_SECONDS));
    }

    protected function now(): int
    {
        return time();
    }

    protected function randomInt(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    protected function loadState(): PolishMedalRuntimeState
    {
        return $this->stateStore()->load();
    }

    protected function saveState(PolishMedalRuntimeState $state): void
    {
        $this->stateStore()->save($state);
    }

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
    protected function executeDelete(array $medal): void
    {
        $medalId = (int)($medal['medal_id'] ?? 0);
        if ($medalId <= 0) {
            return;
        }

        $response = $this->medalManageApi()->deleteMedals([$medalId]);
        $this->authFailureClassifier->assertNotAuthFailure($response, '点亮徽章: 删除已注销勋章时账号未登录');

        $label = $this->medalLabel($medal);
        if ((int)($response['code'] ?? -1) === 0) {
            $this->notice(sprintf('点亮徽章: 删除已注销勋章成功 [%s]', $label));
            return;
        }

        $this->warning(sprintf(
            '点亮徽章: 删除已注销勋章失败 [%s] CODE -> %s MSG -> %s',
            $label,
            (string)($response['code'] ?? ''),
            (string)($response['message'] ?? ''),
        ));
    }

    /**
     * @param array<string, mixed> $medal
     */
    protected function executeLight(array $medal, int $remainingBeforePop): void
    {
        $roomId = (int)($medal['room_id'] ?? 0);
        $targetId = (int)($medal['target_id'] ?? 0);
        if ($roomId <= 0 || $targetId <= 0) {
            return;
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
            return;
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
    }

    protected function handleOutsideWindow(PolishMedalRuntimeState $state, int $now): TaskResult
    {
        $hadPendingRound = $state->roundRefreshedAt() > 0 || $state->hasDeleteQueue() || $state->hasLightQueue();
        if ($hadPendingRound) {
            $state->clearRound();
            $this->saveState($state);
            $this->info('点亮徽章: 当前不在运行窗口内，已丢弃未完成轮次，等待 08:00 重新获取');
        }

        return TaskResult::after($this->window()->secondsUntilNextStart($now));
    }

    protected function medalManageApi(): ApiMedalManage
    {
        return $this->medalManageApi ??= new ApiMedalManage($this->appContext()->request());
    }

    protected function likeInfoApi(): ApiLikeInfoV3
    {
        return $this->likeInfoApi ??= new ApiLikeInfoV3($this->appContext()->request());
    }

    protected function stateStore(): PolishMedalStateStore
    {
        return $this->stateStore ??= new PolishMedalStateStore($this->appContext()->cache());
    }

    protected function window(): PolishMedalWindow
    {
        return $this->window ??= new PolishMedalWindow(self::WINDOW_START, self::WINDOW_END);
    }

    protected function roundPlanner(): PolishMedalRoundPlanner
    {
        return $this->roundPlanner ??= new PolishMedalRoundPlanner(self::MAX_LIGHT_QUEUE_PER_ROUND);
    }

    protected function cleanupInvalidMedalEnabled(): bool
    {
        return $this->config('polish_medal.cleanup_invalid_medal', false, 'bool');
    }

    /**
     * @param array<string, mixed> $medal
     */
    private function medalLabel(array $medal): string
    {
        return sprintf(
            '%s / %s',
            trim((string)($medal['anchor_name'] ?? '未命名主播')),
            trim((string)($medal['medal_name'] ?? '未命名勋章')),
        );
    }

    private function lightProgressLabel(int $remainingBeforePop): string
    {
        if ($remainingBeforePop <= 0) {
            return '';
        }

        return sprintf('(本轮剩余 %d) ', $remainingBeforePop);
    }
}
