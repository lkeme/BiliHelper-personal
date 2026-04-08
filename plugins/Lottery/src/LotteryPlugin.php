<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

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
    private ?LotteryRemoteIndexLoader $remoteIndexLoader = null;

    /**
     * @var array<string, array<int|string, mixed>>
     */
    protected array $config = [
        'cv_list' => [],
        'wait_cv_list' => [],
        'dynamic_list' => [],
        'wait_dynamic_list' => [],
        'lottery_list' => [],
        'wait_lottery_list' => [],
    ];

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

        $this->initConfig();
        $this->handleRemoteIndex();
        $this->handleDynamic();
        $this->handleLottery();
        $this->saveConfig();

        return TaskResult::after(mt_rand(10, 25) * 60);
    }

    protected function initConfig(): void
    {
        $this->config = $this->stateStore()->load();
    }

    protected function saveConfig(): void
    {
        $this->stateStore()->save($this->config);
    }

    protected function handleRemoteIndex(): void
    {
        $added = 0;
        foreach ($this->remoteIndexLoader()->load() as $record) {
            $dynamicId = (int)($record['dynamic_id'] ?? 0);
            if ($dynamicId <= 0 || in_array($dynamicId, $this->config['dynamic_list'], true)) {
                continue;
            }

            $this->addDynamicList($dynamicId);
            $added++;
        }

        $this->info('抽奖: 远程索引同步完成 新增动态 ' . $added . ' 条 当前未处理Count: ' . count($this->config['wait_dynamic_list']));
    }

    protected function handleDynamic(): void
    {
        $this->fetchDynamicReserve();
    }

    protected function handleLottery(): void
    {
        $this->joinLottery();
    }

    protected function joinLottery(): void
    {
        $lottery = array_shift($this->config['wait_lottery_list']);
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

    protected function fetchDynamicReserve(): void
    {
        $dynamicId = array_pop($this->config['wait_dynamic_list']);
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
            $this->extractReserveFromDynamicDetail($data);
        }

        $this->info('抽奖: 获取有效预约列表成功 当前未处理Count: ' . count($this->config['wait_lottery_list']));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function extractReserveFromDynamicDetail(array $data): void
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

        $this->addLotteryList($lottery);
    }

    /**
     * @param array<string, mixed> $lottery
     */
    protected function addLotteryList(array $lottery): void
    {
        $key = "rid{$lottery['rid']}";
        if (!array_key_exists($key, $this->config['lottery_list'])) {
            $this->config['lottery_list'][$key] = $lottery;
            $this->config['wait_lottery_list'][$key] = $lottery;
        }
    }

    protected function addDynamicList(int $dynamic): void
    {
        if (!in_array($dynamic, $this->config['dynamic_list'], true)) {
            $this->config['dynamic_list'][] = $dynamic;
            $this->config['wait_dynamic_list'][] = $dynamic;
        }
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

    private function detailApi(): ApiDetail
    {
        return $this->detailApi ??= new ApiDetail($this->appContext()->request());
    }

    private function remoteIndexLoader(): LotteryRemoteIndexLoader
    {
        return $this->remoteIndexLoader ??= new LotteryRemoteIndexLoader($this->appContext());
    }
}
