<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\DynamicLottery;

use Bhp\ArticleSource\SpaceArticleSourceService;
use Bhp\Api\Dynamic\ApiDetail;
use Bhp\Api\Dynamic\ApiLotteryNotice;
use Bhp\Api\Response\DynamicLotteryNotice;
use Bhp\Api\Response\DynamicReserveLottery;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

final class DynamicLotteryPlugin extends BasePlugin implements PluginTaskInterface
{
    private ?AuthFailureClassifier $authFailureClassifier = null;
    private ?ApiDetail $detailApi = null;
    private ?ApiLotteryNotice $lotteryNoticeApi = null;
    private ?DynamicLotteryStateStore $stateStore = null;
    private ?DynamicLotteryReservationExecutor $reservationExecutor = null;
    private ?SpaceArticleSourceService $articleSourceService = null;
    private ?DynamicLotteryWindow $window = null;

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('dynamic_lottery')) {
            return TaskResult::keepSchedule();
        }

        $now = $this->now();
        $state = DynamicLotteryRuntimeState::bootstrap($this->stateStore()->load());
        $state->resetForBizDate($this->bizDate($now));

        if (!$this->window()->contains($now)) {
            $this->stateStore()->save($state->all());

            return TaskResult::after($this->window()->secondsUntilNextStart($now));
        }

        if (!$state->sourceSynced()) {
            $snapshot = $this->articleSource()->snapshotForToday();
            if (!$snapshot->fetchAttempted) {
                $this->warning('动态抽奖: 当日稿件源暂未就绪，稍后重试');
                $this->stateStore()->save($state->all());

                return TaskResult::after(mt_rand(10, 25) * 60);
            }

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

    protected function joinLottery(DynamicLotteryRuntimeState $state): void
    {
        $lottery = $state->shiftPendingLottery();
        if (!is_array($lottery)) {
            return;
        }

        $this->info("动态抽奖: 执行进度 待参与 {$state->pendingLotteryCount()} 动态 {$lottery['id_str']} RID {$lottery['rid']}");
        $this->info("动态抽奖: 标题: {$lottery['title']}");
        $this->info('动态抽奖: 地址: ' . $this->setDynamicUrl((int)$lottery['id_str']));
        $this->info("动态抽奖: 奖品: {$lottery['prize']}");

        if ($this->filterContentWords((string)$lottery['title']) || $this->filterContentWords((string)$lottery['prize'])) {
            $this->warning('动态抽奖: 标题或奖品命中敏感词，跳过');
            return;
        }

        $result = $this->reserve($lottery);
        if (($result['success'] ?? false) !== true && ($result['retryable'] ?? false) === true) {
            $state->requeueLottery($lottery);
        }
    }

    /**
     * @param array<string, mixed> $info
     * @return array{success: bool, message: string, retryable?: bool}
     */
    protected function reserve(array $info): array
    {
        $result = $this->reservationExecutor()->reserve(
            $info,
            (string)($this->csrf() ?? ''),
            $this->setDynamicUrl((int)$info['id_str']),
        );

        if ($result['success']) {
            $this->notice($result['message']);
            return $result;
        }

        $this->warning($result['message']);

        return $result;
    }

    protected function fetchDynamicReserve(DynamicLotteryRuntimeState $state): void
    {
        $dynamicId = $state->shiftPendingDynamic();
        if (!is_int($dynamicId)) {
            return;
        }

        $dynamicUrl = $this->setDynamicUrl($dynamicId);
        $this->info(sprintf(
            '动态抽奖: 扫描动态进度 (%d/%d) %s',
            max(1, $state->processedDynamicCount()),
            max(1, $state->totalDynamicCount()),
            $dynamicUrl,
        ));

        $response = $this->detailApi()->detail($dynamicId);
        $this->authFailureClassifier()?->assertNotAuthFailure($response, "动态抽奖: 提取动态{$dynamicId}时账号未登录");

        if (($response['code'] ?? -1) !== 0) {
            $state->requeueDynamic($dynamicId);
            $this->warning("动态抽奖: 提取动态({$dynamicId})失败: {$response['code']} -> {$response['message']}，稍后重试");
            return;
        }

        $data = $response['data'] ?? [];
        if (!is_array($data)) {
            $state->requeueDynamic($dynamicId);
            $this->warning("动态抽奖: 提取动态({$dynamicId})返回结构异常，稍后重试");
            return;
        }

        $this->extractReserveFromDynamicDetail($data, $state);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function extractReserveFromDynamicDetail(array $data, DynamicLotteryRuntimeState $state): void
    {
        $lottery = DynamicReserveLottery::fromDetailData($data);
        if (!$lottery instanceof DynamicReserveLottery) {
            $this->info('动态抽奖: 当前动态未发现互动抽奖，跳过');
            return;
        }

        $notice = $this->fetchLotteryNotice((int)$lottery->idStr);
        if (!$notice instanceof DynamicLotteryNotice) {
            $state->requeueDynamic((int)$lottery->idStr);
            $this->warning("动态抽奖: 动态 {$lottery->idStr} 的互动抽奖详情查询失败，稍后重试");
            return;
        }

        if ($notice->participated) {
            $this->info('动态抽奖: 当前账号已参与过该互动抽奖，跳过');
            return;
        }

        if ($notice->status !== 0) {
            $this->info("动态抽奖: 当前互动抽奖状态为 {$notice->status}，跳过");
            return;
        }

        $state->addLottery($lottery->toArray());
        $this->info("动态抽奖: 动态 {$lottery->idStr} 发现互动抽奖，已入队");
    }

    protected function setDynamicUrl(int $dynamicId): string
    {
        return 'https://t.bilibili.com/' . $dynamicId;
    }

    protected function filterContentWords(string $content): bool
    {
        $sensitiveWords = $this->filterWords('DynamicLottery.sensitive', [], 'array');
        foreach ($sensitiveWords as $word) {
            if (is_string($word) && str_contains($content, $word)) {
                return true;
            }
        }

        return false;
    }

    protected function stateStore(): DynamicLotteryStateStore
    {
        return $this->stateStore ??= new DynamicLotteryStateStore($this->cache());
    }

    protected function reservationExecutor(): DynamicLotteryReservationExecutor
    {
        return $this->reservationExecutor ??= new DynamicLotteryReservationExecutor(new DynamicLotteryReservationService(), $this->appContext()->request());
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

    private function lotteryNoticeApi(): ApiLotteryNotice
    {
        return $this->lotteryNoticeApi ??= new ApiLotteryNotice($this->appContext()->request());
    }

    private function fetchLotteryNotice(int $dynamicId): ?DynamicLotteryNotice
    {
        $response = $this->lotteryNoticeApi()->notice($dynamicId);
        $this->authFailureClassifier()?->assertNotAuthFailure($response, "动态抽奖: 查询互动抽奖{$dynamicId}详情时账号未登录");

        return DynamicLotteryNotice::fromResponse($response);
    }

    private function window(): DynamicLotteryWindow
    {
        return $this->window ??= new DynamicLotteryWindow($this->pluginWindowStartAt(), $this->pluginWindowEndAt());
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
