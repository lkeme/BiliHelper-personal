<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\ArticleSource\SpaceArticleSourceService;
use Bhp\Api\Dynamic\ApiDetail;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

final class LotteryPlugin extends BasePlugin implements PluginTaskInterface
{
    private ?AuthFailureClassifier $authFailureClassifier = null;
    private ?ApiDetail $detailApi = null;
    private ?LotteryStateStore $stateStore = null;
    private ?LotteryReservationExecutor $reservationExecutor = null;
    private ?SpaceArticleSourceService $articleSourceService = null;
    private ?LotteryWindow $window = null;

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('lottery')) {
            return TaskResult::keepSchedule();
        }

        $now = $this->now();
        $state = LotteryRuntimeState::bootstrap($this->stateStore()->load());
        $state->resetForBizDate($this->bizDate($now));

        if (!$this->window()->contains($now)) {
            $this->stateStore()->save($state->all());

            return TaskResult::after($this->window()->secondsUntilNextStart($now));
        }

        if (!$state->sourceSynced()) {
            $snapshot = $this->articleSource()->snapshotForToday();
            $state->seedDynamicQueue(
                $snapshot->lotterySourceCvId,
                array_map('intval', $snapshot->lotteryDynamicIds),
            );
        }

        if ($state->pendingDynamicCount() > 0) {
            $this->fetchDynamicReserve($state);
        }
        if ($state->pendingLotteryCount() > 0) {
            $this->joinLottery($state);
        }

        $this->stateStore()->save($state->all());

        if (!$state->hasWork()) {
            return $this->nextPluginStartTaskResult(nextDay: true);
        }

        return TaskResult::after(mt_rand(10, 25) * 60);
    }

    protected function joinLottery(LotteryRuntimeState $state): void
    {
        $lottery = $state->shiftPendingLottery();
        if (!is_array($lottery)) {
            return;
        }

        $this->info("抽奖: 尝试预约 ID: {$lottery['rid']} UP: {$lottery['up_mid']} 预约人数: {$lottery['reserve_total']}");
        $this->info("抽奖: 标题: {$lottery['title']}");
        $this->info('抽奖: 地址: ' . $this->setT((int)$lottery['id_str']));
        $this->info("抽奖: 奖品: {$lottery['prize']}");

        if ($this->filterContentWords((string)$lottery['title']) || $this->filterContentWords((string)$lottery['prize'])) {
            $this->warning('抽奖: 预约失败，标题或描述含有敏感词, 跳过');
            return;
        }

        $this->reserve($lottery);
    }

    /**
     * @param array<string, mixed> $info
     */
    protected function reserve(array $info): void
    {
        $result = $this->reservationExecutor()->reserve(
            $info,
            (string)($this->csrf() ?? ''),
            $this->setT((int)$info['id_str']),
        );

        if ($result['success']) {
            $this->notice($result['message']);
            return;
        }

        $this->warning($result['message']);
    }

    protected function fetchDynamicReserve(LotteryRuntimeState $state): void
    {
        $dynamicId = $state->shiftPendingDynamic();
        if (!is_int($dynamicId)) {
            return;
        }

        $dynamicUrl = $this->setT($dynamicId);
        $this->info("抽奖: 开始提取动态 $dynamicUrl");

        $response = $this->detailApi()->detail($dynamicId);
        $this->authFailureClassifier()?->assertNotAuthFailure($response, "抽奖: 提取动态{$dynamicId}时账号未登录");

        if (($response['code'] ?? -1) !== 0) {
            $this->warning("抽奖: 提取动态({$dynamicId})失败: {$response['code']} -> {$response['message']}");
            return;
        }

        $data = $response['data'] ?? [];
        if (is_array($data)) {
            $this->extractReserveFromDynamicDetail($data, $state);
        }

        $this->info('抽奖: 获取有效预约列表成功 当前未处理Count: ' . $state->pendingLotteryCount());
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function extractReserveFromDynamicDetail(array $data, LotteryRuntimeState $state): void
    {
        if (!isset($data['item']['modules']['module_dynamic']['additional']['reserve'])) {
            $this->warning('抽奖: 提取动态预约失败: 未找到预约信息');
            return;
        }

        if (!(bool)($data['item']['visible'] ?? false)) {
            $this->warning('抽奖: 提取动态预约失败: 动态已不可见');
            return;
        }

        $reserve = $data['item']['modules']['module_dynamic']['additional']['reserve'];
        if (!is_array($reserve)) {
            $this->warning('抽奖: 提取动态预约失败: 未找到预约信息');
            return;
        }

        if (isset($reserve['reserve_record_ctime']) && (int)$reserve['reserve_record_ctime'] > 0) {
            $this->warning('抽奖: 提取动态预约失败: 当前账号已参与过该预约');
            return;
        }

        if (($reserve['button']['uncheck']['text'] ?? null) !== '预约'
            || ($reserve['button']['status'] ?? null) != 1
            || ($reserve['button']['type'] ?? null) != 2) {
            $this->warning('抽奖: 提取动态预约失败: 预约按钮状态异常');
            return;
        }

        if (($reserve['state'] ?? null) != 0 || ($reserve['stype'] ?? null) != 2) {
            $this->warning('抽奖: 提取动态预约失败: 预约动态状态异常');
            return;
        }

        $lottery = [
            'reserve_total' => (int)$reserve['reserve_total'],
            'rid' => (int)$reserve['rid'],
            'title' => (string)$reserve['title'],
            'up_mid' => (int)$reserve['up_mid'],
            'prize' => (string)($reserve['desc3']['text'] ?? ''),
            'id_str' => (string)($data['item']['id_str'] ?? ''),
        ];

        $state->addLottery($lottery);
    }

    protected function setT(int $dynamicId): string
    {
        return 'https://t.bilibili.com/' . $dynamicId;
    }

    protected function filterContentWords(string $content): bool
    {
        $sensitiveWords = $this->filterWords('Lottery.sensitive', [], 'array');
        foreach ($sensitiveWords as $word) {
            if (is_string($word) && str_contains($content, $word)) {
                return true;
            }
        }

        return false;
    }

    protected function stateStore(): LotteryStateStore
    {
        return $this->stateStore ??= new LotteryStateStore($this->cache());
    }

    protected function reservationExecutor(): LotteryReservationExecutor
    {
        return $this->reservationExecutor ??= new LotteryReservationExecutor(new LotteryReservationService(), $this->appContext()->request());
    }

    protected function authFailureClassifier(): ?AuthFailureClassifier
    {
        return $this->authFailureClassifier;
    }

    protected function articleSource(): SpaceArticleSourceService
    {
        return $this->articleSourceService ??= new SpaceArticleSourceService($this->appContext());
    }

    private function detailApi(): ApiDetail
    {
        return $this->detailApi ??= new ApiDetail($this->appContext()->request());
    }

    private function window(): LotteryWindow
    {
        return $this->window ??= new LotteryWindow($this->pluginWindowStartAt(), $this->pluginWindowEndAt());
    }

    private function now(): int
    {
        return time();
    }

    private function bizDate(int $timestamp): string
    {
        return date('Y-m-d', $timestamp);
    }
}
