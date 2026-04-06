<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\LiveGoldBox;

use Bhp\Api\XLive\LotteryInterface\V2\ApiBox;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Exceptions\NoLoginException;

class LiveGoldBoxPlugin extends BasePlugin implements PluginTaskInterface
{
    private const CACHE_SCOPE = 'LiveGoldBox';

    private ?ApiBox $boxApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    protected int $start_aid = 0;

    protected int $stop_aid = 0;

    /**
     * @var int[]
     */
    protected array $invalid_aids = [];

    public function __construct(Plugin &$plugin)
    {
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('live_gold_box')) {
            return TaskResult::keepSchedule();
        }

        try {
            $this->calcAidRange(1216, 1316);
            $lotteryList = $this->fetchLotteryList();
            $this->drawLottery($lotteryList);
        } catch (NoLoginException $e) {
            $this->warning("金色宝箱: {$e->getMessage()}");
            return TaskResult::after(3600);
        }

        return TaskResult::after(mt_rand(6, 10) * 60);
    }

    /**
     * @param array<int, array<string, mixed>> $rounds
     */
    protected function filterRound(array $rounds): int
    {
        foreach ($rounds as $round) {
            $joinStartTime = $round['join_start_time'];
            $joinEndTime = $round['join_end_time'];
            if ($joinEndTime > time() && time() > $joinStartTime) {
                $status = $round['status'];
                if ($status == 0) {
                    return $round['round_num'];
                }
            }
        }

        return 0;
    }

    protected function filterTitleWords(string $title): bool
    {
        $sensitiveWords = $this->filterWords('LiveGoldBox.sensitive', [], 'array');

        foreach ($sensitiveWords as $word) {
            if (is_string($word) && str_contains($title, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{title: string, aid: int, num: int}> $lotteryList
     */
    protected function drawLottery(array $lotteryList): void
    {
        foreach ($lotteryList as $lottery) {
            extract($lottery);

            $response = $this->boxApi()->draw($aid, $num, 0);
            if ($response['code']) {
                $this->warning("金色宝箱: {$title}({$aid}->{$num}) 参与抽奖失败 {$response['code']} -> {$response['message']}~");
            } else {
                $this->notice("金色宝箱: {$title}({$aid}->{$num}) 参与抽奖成功~");
            }
        }
    }

    /**
     * @return array<int, array{title: string, aid: int, num: int}>
     *
     * @throws NoLoginException
     */
    protected function fetchLotteryList(): array
    {
        $this->invalid_aids = ($tmp = $this->cacheGet('invalid_aids', self::CACHE_SCOPE, null)) ? $tmp : [];

        $lotteryList = [];
        $maxProbe = 10;
        $probes = range($this->start_aid, $this->stop_aid);
        foreach ($probes as $probeAid) {
            if ($maxProbe == 0) {
                break;
            }
            if (in_array($probeAid, $this->invalid_aids, true)) {
                continue;
            }
            $response = $this->boxInfos($probeAid);
            if (empty($response)) {
                $maxProbe--;
                continue;
            }

            $rounds = $response['typeB'];
            $lastRound = end($rounds);
            if ($lastRound['join_end_time'] < time()) {
                $this->invalid_aids[] = $probeAid;
                continue;
            }
            $title = $response['title'];
            if ($this->filterTitleWords($title)) {
                $this->invalid_aids[] = $probeAid;
                continue;
            }
            $roundNum = $this->filterRound($rounds);
            if ($roundNum == 0) {
                continue;
            }
            $lotteryList[] = [
                'title' => $title,
                'aid' => $probeAid,
                'num' => $roundNum,
            ];
        }
        $this->cacheSet('invalid_aids', $this->invalid_aids, self::CACHE_SCOPE);
        $this->info('金色宝箱: 获取到有效抽奖列表 ' . count($lotteryList));

        return $lotteryList;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws NoLoginException
     */
    protected function boxInfos(int $aid): array
    {
        $response = $this->boxApi()->getStatus($aid);

        switch ($response['code']) {
            case -500:
                throw new NoLoginException($response['message']);
            case 0:
                if (is_null($response['data'])) {
                    return [];
                }

                return $response['data'];
            default:
                $this->warning("金色宝箱: 获取宝箱{$aid}状态失败 {$response['code']} -> {$response['message']}");
                return [];
        }
    }

    /**
     * @throws NoLoginException
     */
    protected function calcAidRange(int $min, int $max): void
    {
        if ($this->start_aid && $this->stop_aid) {
            return;
        }

        while (true) {
            $middle = intval(($min + $max) / 2);
            $info = $this->boxInfos($middle);
            if (empty($info)) {
                $info = $this->boxInfos($middle + mt_rand(0, 3));
                if (empty($info)) {
                    $max = $middle;
                } else {
                    $min = $middle;
                }
            } else {
                $min = $middle;
            }
            if ($max - $min == 1) {
                break;
            }
        }

        $this->start_aid = $min - mt_rand(15, 30);
        $this->stop_aid = $min + mt_rand(15, 30);
        $this->info("金色宝箱: 设置穷举范围({$this->start_aid} -> {$this->stop_aid})");
    }
    private function boxApi(): ApiBox
    {
        return $this->boxApi ??= new ApiBox($this->appContext()->request());
    }
}
