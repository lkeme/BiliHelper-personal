<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class Judge
{
    use TimeLock;

    private static $retry_time = 0;
    private static $wait_case_id = 0;
    private static $wait_time = 0;
    private static $min_ok_pct = 1;
    private static $max_ok_pct = 0;

    public static function run()
    {
        if (self::getLock() > time() || self::$retry_time > time() || getenv('USE_JUDGE') == 'false') {
            return;
        }
        # https://www.bilibili.com/judgement/index
        $case_id = self::$wait_case_id ? self::$wait_case_id : self::caseObtain();
        if (!self::judgeCase($case_id)) {
            self::setLock(1 * 60 + 5);
            return;
        }
        //  self::judgementIndex();
        self::setLock(mt_rand(15, 30) * 60);
    }

    /**
     * @use 判案 TODO: 处理案例已满(MAX20例)
     * @param $case_id
     * @return bool
     */
    private static function judgeCase($case_id)
    {
        if (is_null($case_id) || $case_id == 0) {
            return true;
        }
        // Log::info("尝试判定案件 {$case_id}");
        $data = self::judgementVote($case_id);
        $num_judged = $data['num_voted'];
        $ok_pct = $data['ok_percent'];
        $advice = self::judgeAdvice($num_judged, $ok_pct);
        if ($num_judged >= 50) {

            self::$min_ok_pct = min(self::$min_ok_pct, $ok_pct);
            self::$max_ok_pct = max(self::$max_ok_pct, $ok_pct);
            // user.info('更新统计投票波动情况')
        }
        // Log::info("案件 {$case_id} 已经等待" . self::$wait_time . "s，统计波动区间为" . self::$min_ok_pct . "-" . self::$max_ok_pct);
        if (is_null($advice)) {
            if (self::$wait_time >= 1200) {
                // 如果case判定中，波动很小，则表示趋势基本一致
                if ((self::$max_ok_pct - self::$min_ok_pct) >= 0 && (self::$max_ok_pct - self::$min_ok_pct) <= 0.1 && $num_judged > 200) {
                    $num_judged += 100;
                    $advice0 = self::judgeAdvice($num_judged, self::$max_ok_pct);
                    $advice1 = self::judgeAdvice($num_judged, self::$min_ok_pct);
                    $advice = ($advice0 == $advice1) ? $advice0 : null;
                }
                Log::info("判定结果 {$advice}");
            } else {
                $sleep_wait_time = ($num_judged < 300) ? 200 : 60;
                Log::info("案件 {$case_id} 暂无法判定，{$sleep_wait_time}S 后重新尝试");
                self::$wait_time += $sleep_wait_time;
                self::$retry_time = $sleep_wait_time + time();
                self::$wait_case_id = $case_id;
                return false;
            }
        }
        // 如果还不行就放弃
        $decision = !is_null($advice) ? $advice : 3;
        $dicision_info = ($decision == 3) ? '作废票' : '有效票';
        Log::info("案件 {$case_id} 的投票结果：{$dicision_info}({$decision})");
        self::juryVote($case_id, $decision);
        self::initParams();
        return true;
    }

    /**
     * @use 投票建议
     * @param $num_judged
     * @param $pct
     * @return int|null
     */
    private static function judgeAdvice($num_judged, $pct)
    {
        if ($num_judged >= 300) {
            # 认为这里可能出现了较多分歧，抬一手
            if ($pct >= 0.4) {
                return 2;
            } elseif ($pct <= 0.25) {
                return 4;
            } else {
                return null;
            }
        } elseif ($num_judged >= 150) {
            if ($pct >= 0.9) {
                return 2;
            } elseif ($pct <= 0.1) {
                return 4;
            } else {
                return null;
            }
        } elseif ($num_judged >= 50) {
            if ($pct >= 0.97) {
                return 2;
            } elseif ($pct <= 0.03) {
                return 4;
            } else {
                return null;
            }
        }
        # 抬一手
        if ($num_judged >= 400) {
            return 2;
        }
        return null;
    }

    /**
     * @use 投票
     * @param $case_id
     * @param $decision
     */
    private static function juryVote($case_id, $decision)
    {
        $user_info = User::parseCookies();
        $url = 'http://api.bilibili.com/x/credit/jury/vote';
        $payload = [
            "jsonp" => "jsonp",
            "cid" => $case_id,
            "vote" => $decision,
            "content" => "",
            "likes" => "",
            "hates" => "",
            "attr" => "1",
            "csrf" => $user_info['token'],
        ];
        $raw = Curl::post('pc', $url, $payload);
        $de_raw = json_decode($raw, true);
        if (isset($de_raw['code']) && $de_raw['code']) {
            Log::warning("案件 {$case_id} 投票失败 {$raw}");
        } else {
            Log::notice("案件 {$case_id} 投票成功 {$raw}");
        }
    }


    /**
     * @use 案件获取
     * @return |null
     */
    private static function caseObtain()
    {
        $user_info = User::parseCookies();
        $url = 'http://api.bilibili.com/x/credit/jury/caseObtain';
        $payload = [
            "jsonp" => "jsonp",
            "csrf" => $user_info['token']
        ];
        $raw = Curl::post('pc', $url, $payload);
        $de_raw = json_decode($raw, true);
        // {"code":25008,"message":"真给力 , 移交众裁的举报案件已经被处理完了","ttl":1}
        // {"code":25014,"message":"25014","ttl":1}
        // {"code":25005,"message":"请成为风纪委员后再试","ttl":1}
        if (isset($de_raw['code']) && $de_raw['code'] == 25005) {
            Log::warning($de_raw['message']);
            self::setLock(self::timing(10));
            return null;
        }
        if (isset($de_raw['code']) && $de_raw['code']) {
            Log::info("没有获取到案件~ {$raw}");
            return null;
        } else {
            $case_id = $de_raw['data']['id'];
            Log::info("获取到案件 {$case_id} ~");
            return $case_id;
        }
    }

    /**
     * @use 判断投票
     * @param $case_id
     * @return array
     */
    private static function judgementVote($case_id)
    {
        $url = 'https://api.bilibili.com/x/credit/jury/juryCase';
        $headers = [
            'Referer' => "https://www.bilibili.com/judgement/vote/{$case_id}"
        ];
        $payload = [
            'callback' => "jQuery1720" . self::randInt() . "_" . time(),
            'cid' => $case_id,
            '_' => time()
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        $data = $de_raw['data'];
        # 3 放弃
        # 2 否 vote_rule
        # 4 删除 vote_delete
        # 1 封杀 vote_break
        $vote_break = $data['voteBreak'];
        $vote_delete = $data['voteDelete'];
        $vote_rule = $data['voteRule'];
        $num_voted = $vote_break + $vote_delete + $vote_rule;
        $ok_percent = $num_voted ? ($vote_rule / $num_voted) : 0;
        // 言论合理比例 {$ok_percent}
        Log::info("案件 {$case_id} 目前已投票 {$num_voted}");
        return [
            'num_voted' => $num_voted,
            'ok_percent' => $ok_percent
        ];
    }


    /**
     * @use 获取案例数据|风纪检测
     * @return bool
     */
    private static function judgementIndex()
    {
        $url = 'https://api.bilibili.com/x/credit/jury/caseList';
        $headers = [
            'Referer' => "https://www.bilibili.com/judgement/index"
        ];
        $payload = [
            'callback' => "jQuery1720" . self::randInt() . "_" . time(),
            'pn' => 1,
            'ps' => 25,
            '_' => time()
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        print_r($de_raw);
        $data = $de_raw['data'];
        if (!$data) {
            Log::info('该用户非风纪委成员');
            return false;
        }
        $today = date("Y-m-d");
        $sum_cases = 0;
        $valid_cases = 0;
        $judging_cases = 0;
        foreach ($data as $case) {
            $ts = $case['voteTime'] / 1000;
            $vote_day = date("Y-m-d", $ts);
            if ($vote_day == $today) {
                $sum_cases += 1;
                $vote = $case['vote'];
                if ($vote) {
                    $valid_cases += 1;
                } else {
                    $judging_cases += 1;
                }
            }
        }
        Log::info("今日投票{$sum_cases}（{$valid_cases}票有效（非弃权），{$judging_cases}票还在进行中）");
        return true;
    }


    /**
     * @use 随机整数
     * @param int $max
     * @return string
     */
    private static function randInt(int $max = 17): string
    {
        $temp = [];
        foreach (range(1, $max) as $index) {
            array_push($temp, mt_rand(0, 9));
        }
        return implode("", $temp);
    }

    /**
     * @use 初始化参数
     */
    private static function initParams()
    {
        self::$retry_time = 0;
        self::$wait_case_id = 0;
        self::$wait_time = 0;
        self::$min_ok_pct = 1;
        self::$max_ok_pct = 0;
    }
}