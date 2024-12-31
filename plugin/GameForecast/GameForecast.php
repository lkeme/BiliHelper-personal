<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

use Bhp\Api\Esports\ApiGuess;
use Bhp\Log\Log;
use Bhp\Plugin\BasePlugin;
use Bhp\Plugin\Plugin;
use Bhp\TimeLock\TimeLock;

class GameForecast extends BasePlugin
{
    /**
     * 插件信息
     * @var array|string[]
     */
    public ?array $info = [
        'hook' => __CLASS__, // hook
        'name' => 'GameForecast', // 插件名称
        'version' => '0.0.1', // 插件版本
        'desc' => '赛事预测(破产机)', // 插件描述
        'author' => 'Lkeme',// 作者
        'priority' => 1104, // 插件优先级
        'cycle' => '24(小时)', // 运行周期
    ];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin &$plugin)
    {
        //
        TimeLock::initTimeLock();
        // $this::class
        $plugin->register($this, 'execute');
    }

    /**
     * 执行
     * @return void
     */
    public function execute(): void
    {
        if (TimeLock::getTimes() > time() || !getEnable('game_forecast')) return;
        //
        $this->startStake();
        //
        TimeLock::setTimes(TimeLock::timing(1, 30));
    }

    /**
     * 获取预测赛事列表
     * @param int $pm
     * @return array
     */
    protected function fetchCollectionQuestions(int $pm = 10): array
    {
        $questions = [];
        for ($i = 1; $i < $pm; $i++) {
            $response = ApiGuess::collectionQuestion($i);
            //
            if ($response['code']) {
                Log::warning("赛事预测: 获取赛事列表失败 {$response['code']} -> {$response['message']}");
                break;
            }
            //
            if (count($response['data']['list']) == 0) {
                break;
            }
            // 添加到集合
            foreach ($response['data']['list'] as $question) {
                // 判断是否有效 正2分钟
                if (($question['contest']['stime'] - 600 - 120) > time()) {
                    $questions[] = $question;
                }
            }
            // 和页面的不匹配 跳出
            if (count($response['data']['list']) != $response['data']['page']['size']) {
                break;
            }
        }
        Log::info('赛事预测: 获取到 ' . count($questions) . ' 个有效赛事');
        return $questions;
    }

    /**
     * 预计猜测结果
     * @param array $question
     * @return array
     */
    protected function parseQuestion(array $question): array
    {
        $guess = [];
        $guess['oid'] = $question['contest']['id'];
        $guess['main_id'] = $question['questions'][0]['id'];
        $details = $question['questions'][0]['details'];
        $guess['count'] = (($count = getConf('game_forecast.max_coin', 0, 'int')) <= 10) ? $count : 10;
        $guess['title'] = $question['questions'][0]['title'];
        foreach ($details as $detail) {
            $guess['title'] .= " 队伍: {$detail['option']} 赔率: {$detail['odds']}";
        }
        array_multisort(array_column($details, "odds"), SORT_ASC, $details);
        switch (getConf('game_forecast.bet', 3, 'int')) {
            case 1:
                // 压大
                $detail = array_pop($details);
                break;
            case 2:
                // 压小
                $detail = array_shift($details);
                break;
            case 3:
                // 随机
                $detail = $details[array_rand($details)];
                break;
            default:
                // 乱序
                shuffle($details);
                $detail = $details[array_rand($details)];
                break;
        }
        $guess['detail_id'] = $detail['detail_id'];
        $profit = ceil($guess['count'] * $detail['odds']);
        $guess['estimate'] = "竞猜队伍: {$detail['option']} 预计下注: {$guess['count']} 预计赚取: $profit 预计亏损: {$guess['count']} (硬币)";
        return $guess;
    }

    /**
     * 开始破产
     */
    protected function startStake(): void
    {
        //
        $max_guess = getConf('game_forecast.max_num', 0, 'int');
        $max_coin = getConf('game_forecast.max_coin', 0, 'int');
        if ($max_guess <= 0 || $max_coin <= 0) {
            Log::warning('赛事预测: 每日竞猜次数或者硬币数量不能小于1');
            return;
        }
        //
        $questions = $this->fetchCollectionQuestions();

        foreach ($questions as $index => $question) {
            if ($index >= $max_guess) {
                break;
            }
            $guess = $this->parseQuestion($question);
            $this->addGuess($guess);
        }
    }

    /**
     * 添加竞猜
     * @param array $guess
     */
    protected function addGuess(array $guess): void
    {
        Log::info($guess['title']);
        Log::info($guess['estimate']);
        //
        $response = ApiGuess::guessAdd($guess['oid'], $guess['main_id'], $guess['detail_id'], $guess['count']);
        // {"code":0,"message":"0","ttl":1}
        if ($response['message'] == 0) {
            Log::notice('赛事预测: 破产成功');
        } else {
            Log::warning("赛事预测: 破产失败 {$response['code']} -> {$response['message']}");
        }
    }
}
