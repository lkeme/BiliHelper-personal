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
    private const CACHE_SCOPE = 'GameForecast';
    private const HANDLED_CACHE_KEY = 'handled_questions';
    private const HANDLED_CACHE_DATE_KEY = 'handled_questions_date';
    /** @var int[] */
    private const NON_FATAL_HANDLED_CODES = [75207];

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

        return TaskResult::nextDayAt(1, 30, 0, 1, 30);
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
        $handledQuestions = $this->loadHandledQuestions();
        $handledChanged = false;
        $processed = 0;

        foreach ($questions as $question) {
            if ($processed >= $maxGuess) {
                break;
            }

            $questionKey = $this->questionKey($question);
            if ($questionKey !== '' && isset($handledQuestions[$questionKey])) {
                $this->info('赛事预测: 跳过今日已处理赛事 ' . $this->questionLabel($question));
                continue;
            }

            $guess = $this->parseQuestion($question);
            if ($this->addGuess($guess) && $questionKey !== '') {
                $handledQuestions[$questionKey] = true;
                $handledChanged = true;
            }
            $processed++;
        }

        if ($handledChanged) {
            $this->saveHandledQuestions($handledQuestions);
        }
    }

    /**
     * @param array<string, int|string> $guess
     */
    protected function addGuess(array $guess): bool
    {
        $this->info((string)$guess['title']);
        $this->info((string)$guess['estimate']);

        $response = $this->guessApi()->guessAdd((int)$guess['oid'], (int)$guess['main_id'], (int)$guess['detail_id'], (int)$guess['count']);
        $this->authFailureClassifier->assertNotAuthFailure($response, '赛事预测: 执行竞猜时账号未登录');
        $code = (int)($response['code'] ?? -1);
        if ($response['message'] == 0) {
            $this->notice('赛事预测: 破产成功');

            return true;
        }

        $this->warning("赛事预测: 破产失败 {$response['code']} -> {$response['message']}");
        if (in_array($code, self::NON_FATAL_HANDLED_CODES, true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function loadHandledQuestions(): array
    {
        $today = date('Y-m-d');
        $cacheDate = (string)$this->cacheGet(self::HANDLED_CACHE_DATE_KEY, self::CACHE_SCOPE, '');
        $handled = $this->cacheGet(self::HANDLED_CACHE_KEY, self::CACHE_SCOPE, []);
        if ($cacheDate !== $today || !is_array($handled)) {
            return [];
        }

        $normalized = [];
        foreach ($handled as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = true;
                continue;
            }
            if (is_string($value)) {
                $normalized[$value] = true;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, true> $handledQuestions
     */
    private function saveHandledQuestions(array $handledQuestions): void
    {
        $this->cacheSet(self::HANDLED_CACHE_DATE_KEY, date('Y-m-d'), self::CACHE_SCOPE);
        $this->cacheSet(self::HANDLED_CACHE_KEY, $handledQuestions, self::CACHE_SCOPE);
    }

    /**
     * @param array<string, mixed> $question
     */
    private function questionKey(array $question): string
    {
        $oid = (int)($question['contest']['id'] ?? 0);
        $mainId = (int)($question['questions'][0]['id'] ?? 0);
        if ($oid <= 0 || $mainId <= 0) {
            return '';
        }

        return $oid . ':' . $mainId;
    }

    /**
     * @param array<string, mixed> $question
     */
    private function questionLabel(array $question): string
    {
        return trim((string)($question['questions'][0]['title'] ?? '未命名赛事')) ?: '未命名赛事';
    }

    private function guessApi(): ApiGuess
    {
        return $this->guessApi ??= new ApiGuess($this->appContext()->request());
    }
}
