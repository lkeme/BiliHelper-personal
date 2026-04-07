<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\Api\Space\ApiArticle;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Common\Common;
use Bhp\Util\Exceptions\RequestException;

class LotteryPlugin extends BasePlugin implements PluginTaskInterface
{
    private ?AuthFailureClassifier $authFailureClassifier = null;
    private ?ApiArticle $articleApi = null;
    private ?\Bhp\Api\Dynamic\ApiDetail $detailApi = null;

    private ?LotteryStateStore $stateStore = null;

    private ?LotteryReservationExecutor $reservationExecutor = null;

    /**
     * @var array<string, int|string>
     */

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
        $globalUid = base64_decode('MTkwNTcwMjM3NQ==');
        if ($globalUid === false) {
            return TaskResult::after(mt_rand(10, 25) * 60);
        }

        $this->handleArticle($globalUid);
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

    protected function handleArticle(string $uid): void
    {
        $this->fetchValidArticleUrls($uid);
        $this->fetchValidDynamicUrl($uid);
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

    protected function fetchValidDynamicUrl(string $uid): void
    {
        $cv = array_shift($this->config['wait_cv_list']);
        if (!is_int($cv)) {
            return;
        }

        $url = $this->setCv($cv);

        $this->info("抽奖: 开始提取专栏 $url");
        try {
            $response = $this->requestGet('pc', $url, [], [
                'referer' => "https://space.bilibili.com/$uid/",
            ]);
        } catch (RequestException $exception) {
            $this->warning("抽奖: 提取专栏失败，网络异常，跳过 Error: {$exception->getMessage()}");

            return;
        }

        $this->_fetchValidDynamicUrl($response);

        $this->info('抽奖: 获取有效动态列表成功 当前未处理Count: ' . count($this->config['wait_dynamic_list']));
    }

    /**
     * @return array<int, string>
     */
    protected function _fetchValidDynamicUrl(string $data): array
    {
        $urls = [];
        preg_match_all('/https:\/\/t\.bilibili\.com\/[0-9]+/', $data, $matches);

        foreach ($matches[0] as $url) {
            if (!is_string($url)) {
                continue;
            }

            $dynamicId = $this->getT($url);
            if (in_array($dynamicId, $this->config['dynamic_list'], true)) {
                continue;
            }

            $this->addDynamicList($dynamicId);
            $urls[] = $url;
        }

        return $urls;
    }

    protected function fetchValidArticleUrls(string $uid): void
    {
        $response = $this->articleApi()->article($uid);
        $this->authFailureClassifier()?->assertNotAuthFailure($response, '抽奖: 获取有效专栏列表时账号未登录');

        if (($response['code'] ?? -1) === 0) {
            $data = $response['data'] ?? [];
            if (is_array($data)) {
                $this->_fetchValidArticleUrls($data);
            }

            $this->info('抽奖: 获取有效专栏列表成功 当前未处理Count: ' . count($this->config['wait_cv_list']));

            return;
        }

        $this->warning("抽奖: 获取有效专栏列表失败 Error: {$response['code']} -> {$response['message']}");
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function _fetchValidArticleUrls(array $data): void
    {
        foreach ($data['articles'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!Common::isTimestampInToday((int)($item['publish_time'] ?? 0))) {
                continue;
            }

            $title = (string)($item['title'] ?? '');
            if (!str_contains($title, '抽奖') && !str_contains($title, '预约')) {
                continue;
            }

            $id = (int)($item['id'] ?? 0);
            if ($id <= 0 || in_array($id, $this->config['cv_list'], true)) {
                continue;
            }

            $this->addCvList($id);
        }
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
            $this->_fetchDynamicReserve($data);
        }

        $this->info('抽奖: 获取有效预约列表成功 当前未处理Count: ' . count($this->config['wait_lottery_list']));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function _fetchDynamicReserve(array $data): void
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

    protected function addCvList(int $cv): void
    {
        if (!in_array($cv, $this->config['cv_list'], true)) {
            $this->config['cv_list'][] = $cv;
            $this->config['wait_cv_list'][] = $cv;
        }
    }

    protected function addDynamicList(int $dynamic): void
    {
        if (!in_array($dynamic, $this->config['dynamic_list'], true)) {
            $this->config['dynamic_list'][] = $dynamic;
            $this->config['wait_dynamic_list'][] = $dynamic;
        }
    }

    protected function getCv(string $url): int
    {
        return (int)str_replace('https://www.bilibili.com/read/cv', '', $url);
    }

    protected function setCv(int $cv): string
    {
        return 'https://www.bilibili.com/read/cv' . $cv;
    }

    protected function getT(string $url): int
    {
        return (int)str_replace('https://t.bilibili.com/', '', $url);
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

    private function articleApi(): ApiArticle
    {
        return $this->articleApi ??= new ApiArticle($this->appContext()->request());
    }

    private function detailApi(): \Bhp\Api\Dynamic\ApiDetail
    {
        return $this->detailApi ??= new \Bhp\Api\Dynamic\ApiDetail($this->appContext()->request());
    }
}
