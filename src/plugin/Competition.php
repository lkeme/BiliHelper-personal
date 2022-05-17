<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class Competition
{
    use TimeLock;

    /**
     * @use run
     * @doc 赛事入口 https://www.bilibili.com/v/game/match/competition
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('match_forecast')) {
            return;
        }
        self::startStake();
        self::setLock(self::timing(1, 30));
    }

    /**
     * @use 开始破产
     */
    private static function startStake(): void
    {
        $questions = self::fetchQuestions();
        $max_guess = getConf('max_num', 'match_forecast');
        foreach ($questions as $index => $question) {
            if ($index >= $max_guess) {
                break;
            }
            $guess = self::parseQuestion($question);
            self::addGuess($guess);
        }
    }

    /**
     * @use 添加竞猜
     * @param array $guess
     */
    private static function addGuess(array $guess): void
    {
        Log::info($guess['title']);
        Log::info($guess['estimate']);
        $url = 'https://api.bilibili.com/x/esports/guess/add';
        $payload = [
            'oid' => $guess['oid'],
            'main_id' => $guess['main_id'],
            'detail_id' => $guess['detail_id'],
            'count' => $guess['count'],
            'is_fav' => 0,
            'csrf' => getCsrf()
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/v/game/match/competition'
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1}
        if ($de_raw['code'] == 0) {
            Log::notice("破产成功: {$de_raw['message']}");
        } else {
            Log::warning("破产失败: $raw");
        }
    }

    /**
     * @use 预计猜测结果
     * @param array $question
     * @return array
     */
    private static function parseQuestion(array $question): array
    {
        $guess = [];
        $guess['oid'] = $question['contest']['id'];
        $guess['main_id'] = $question['questions'][0]['id'];
        $details = $question['questions'][0]['details'];
        $guess['count'] = (($count = getConf('max_coin', 'match_forecast')) <= 10) ? $count : 10;
        $guess['title'] = $question['questions'][0]['title'];
        foreach ($details as $detail) {
            $guess['title'] .= " 队伍: {$detail['option']} 赔率: {$detail['odds']}";
        }
        array_multisort(array_column($details, "odds"), SORT_ASC, $details);
        switch (getConf('bet', 'match_forecast')) {
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
     * @use 获取所有问题
     * @param int $page_max
     * @return array
     */
    private static function fetchQuestions(int $page_max = 10): array
    {
        $questions = [];
        $url = 'https://api.bilibili.com/x/esports/guess/collection/question';
        for ($i = 1; $i < $page_max; $i++) {
            $payload = [
                'pn' => $i,
                'ps' => 50,
                'stime' => date("Y-m-d H:i:s", strtotime(date("Y-m-d", time()))),
                'etime' => date("Y-m-d H:i:s", strtotime(date("Y-m-d", time())) + 86400 - 1)
            ];
            $headers = [
                'origin' => 'https://www.bilibili.com',
                'referer' => 'https://www.bilibili.com/v/game/match/competition',
            ];
            $raw = Curl::get('pc', $url, $payload, $headers);
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] == 0 && isset($de_raw['data']['list'])) {
                // 为空跳出
                if (count($de_raw['data']['list']) == 0) {
                    break;
                }
                // 添加到集合
                foreach ($de_raw['data']['list'] as $question) {
                    // 判断是否有效 正2分钟
                    if (($question['contest']['stime'] - 600 - 120) > time()) {
                        $questions[] = $question;
                    }
                }
                // 和页面的不匹配 跳出
                if (count($de_raw['data']['list']) != $de_raw['data']['page']['size']) {
                    break;
                }
            } else {
                // 错误跳出
                break;
            }
        }
        Log::info('获取到 ' . count($questions) . ' 个有效竞猜');
        return $questions;
    }


}