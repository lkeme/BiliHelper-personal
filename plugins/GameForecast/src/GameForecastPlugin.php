<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\GameForecast;

use Bhp\Api\Esports\ApiGuess;
use Bhp\Login\AuthFailureClassifier;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Contract\PluginTaskInterface;
use Bhp\Plugin\Plugin;
use Bhp\Scheduler\TaskResult;

class GameForecastPlugin extends BasePlugin implements PluginTaskInterface
{
    private AuthFailureClassifier $authFailureClassifier;
    private ?ApiGuess $guessApi = null;

    /**
     * 插件信息
     *
     * @var array<string, int|string>
     */

    public function __construct(Plugin &$plugin)
    {
        $this->authFailureClassifier = new AuthFailureClassifier();
        $this->bootPlugin($plugin, true);
    }

    public function runOnce(): TaskResult
    {
        if (!$this->enabled('game_forecast')) {
            return TaskResult::keepSchedule();
        }

        $this->startStake();

        return TaskResult::nextAt(1, 30);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchCollectionQuestions(int $pm = 10): array
    {
        $questions = [];
        for ($i = 1; $i < $pm; $i++) {
            $response = $this->guessApi()->collectionQuestion($i);
            $this->authFailureClassifier->assertNotAuthFailure($response, '赛事预测: 获取赛事列表时账号未登录');
            if ($response['code']) {
                $this->warning("赛事预测: 获取赛事列表失败 {$response['code']} -> {$response['message']}");
                break;
            }
            if (count($response['data']['list']) == 0) {
                break;
            }
            foreach ($response['data']['list'] as $question) {
                if (($question['contest']['stime'] - 600 - 120) > time()) {
                    $questions[] = $question;
                }
            }
            if (count($response['data']['list']) != $response['data']['page']['size']) {
                break;
            }
        }
        $this->info('赛事预测: 获取到 ' . count($questions) . ' 个有效赛事');

        return $questions;
    }

    /**
     * @param array<string, mixed> $question
     * @return array<string, int|string>
     */
    protected function parseQuestion(array $question): array
    {
        $guess = [];
        $guess['oid'] = $question['contest']['id'];
        $guess['main_id'] = $question['questions'][0]['id'];
        $details = $question['questions'][0]['details'];
        $guess['count'] = (($count = $this->config('game_forecast.max_coin', 0, 'int')) <= 10) ? $count : 10;
        $guess['title'] = $question['questions'][0]['title'];
        foreach ($details as $detail) {
            $guess['title'] .= " 队伍: {$detail['option']} 赔率: {$detail['odds']}";
        }
        array_multisort(array_column($details, 'odds'), SORT_ASC, $details);
        switch ($this->config('game_forecast.bet', 3, 'int')) {
            case 1:
                $detail = array_pop($details);
                break;
            case 2:
                $detail = array_shift($details);
                break;
            case 3:
                $detail = $details[array_rand($details)];
                break;
            default:
                shuffle($details);
                $detail = $details[array_rand($details)];
                break;
        }
        $guess['detail_id'] = $detail['detail_id'];
        $profit = ceil($guess['count'] * $detail['odds']);
        $guess['estimate'] = "竞猜队伍: {$detail['option']} 预计下注: {$guess['count']} 预计赚取: {$profit} 预计亏损: {$guess['count']} (硬币)";

        return $guess;
    }

    protected function startStake(): void
    {
        $maxGuess = $this->config('game_forecast.max_num', 0, 'int');
        $maxCoin = $this->config('game_forecast.max_coin', 0, 'int');
        if ($maxGuess <= 0 || $maxCoin <= 0) {
            $this->warning('赛事预测: 每日竞猜次数或者硬币数量不能小于1');
            return;
        }

        $questions = $this->fetchCollectionQuestions();

        foreach ($questions as $index => $question) {
            if ($index >= $maxGuess) {
                break;
            }
            $guess = $this->parseQuestion($question);
            $this->addGuess($guess);
        }
    }

    /**
     * @param array<string, int|string> $guess
     */
    protected function addGuess(array $guess): void
    {
        $this->info((string)$guess['title']);
        $this->info((string)$guess['estimate']);

        $response = $this->guessApi()->guessAdd((int)$guess['oid'], (int)$guess['main_id'], (int)$guess['detail_id'], (int)$guess['count']);
        $this->authFailureClassifier->assertNotAuthFailure($response, '赛事预测: 执行竞猜时账号未登录');
        if ($response['message'] == 0) {
            $this->notice('赛事预测: 破产成功');
        } else {
            $this->warning("赛事预测: 破产失败 {$response['code']} -> {$response['message']}");
        }
    }
    private function guessApi(): ApiGuess
    {
        return $this->guessApi ??= new ApiGuess($this->appContext()->request());
    }
}
