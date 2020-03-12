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

class MaterialObject
{
    use TimeLock;

    private static $invalid_aids = [];
    private static $start_aid = 0;
    private static $end_aid = 0;

    public static function run()
    {
        if (getenv('USE_MO') == 'false') {
            return;
        }
        if (self::getLock() > time()) {
            return;
        }
        // TODO 优化计算AID算法
        self::calcAid(470, 770);
        $lottery_list = self::fetchLottery();
        self::drawLottery($lottery_list);
        self::setLock(random_int(5, 10) * 60);
    }


    /**
     * @use 过滤抽奖Title
     * @param string $title
     * @return bool
     */
    private static function filterTitleWords(string $title): bool
    {
        $sensitive_words = [
            '测试', '加密', 'test', 'TEST', '钓鱼', '炸鱼', '调试', "123", "1111", "测试", "測試", "Test",
            "测一测", "ce-shi", "test", "T-E-S-T", "lala", "我是抽奖标题", "压测", "測一測", "t-e-s-t"
        ];
        foreach ($sensitive_words as $word) {
            if (strpos($title, $word) !== false) {
                return true;
            }
        }
        return false;
    }


    /**
     * @use 抽奖盒子状态
     * @param int $aid
     * @param string $reply
     * @return array|bool|mixed
     */
    private static function boxStatus(int $aid, $reply = 'bool')
    {
        $url = 'https://api.live.bilibili.com/lottery/v1/box/getStatus';
        $payload = [
            'aid' => $aid,
        ];
        $raw = Curl::get('pc', $url, $payload);
        $de_raw = json_decode($raw, true);
        switch ($reply) {
            // 等于0是有抽奖返回false
            case 'bool':
                if ($de_raw['code'] == 0) {
                    return false;
                }
                return true;
            case 'array':
                if ($de_raw['code'] == 0) {
                    return $de_raw;
                }
                return [];
            default:
                return $de_raw;
        }
    }


    /**
     * @use 获取抽奖
     * @return array
     */
    private static function fetchLottery(): array
    {
        $lottery_list = [];
        $max_probe = 10;
        $probes = range(self::$start_aid, self::$end_aid);
        foreach ($probes as $probe_aid) {
            // 最大试探
            if ($max_probe == 0) break;
            // 无效列表
            if (in_array($probe_aid, self::$invalid_aids)) {
                continue;
            }
            // 试探
            $response = self::boxStatus($probe_aid, 'array');
            if (empty($response)) {
                $max_probe--;
                continue;
            }
            $rounds = $response['data']['typeB'];
            $last_round = end($rounds);
            // 最后抽奖轮次无效
            if ($last_round['join_end_time'] < time()) {
                array_push(self::$invalid_aids, $probe_aid);
                continue;
            }
            // 过滤敏感词
            $title = $response['data']['title'];
            if (self::filterTitleWords($title)) {
                array_push(self::$invalid_aids, $probe_aid);
                continue;
            }
            // 过滤抽奖轮次
            $round_num = self::filterRound($rounds);
            if ($round_num == 0) {
                continue;
            }
            array_push($lottery_list, [
                'aid' => $probe_aid,
                'num' => $round_num,
            ]);
        }
        return $lottery_list;
    }


    /**
     * @use 过滤轮次
     * @param array $rounds
     * @return int
     */
    private static function filterRound(array $rounds): int
    {
        foreach ($rounds as $round) {
            $join_start_time = $round['join_start_time'];
            $join_end_time = $round['join_end_time'];
            if ($join_end_time > time() && time() > $join_start_time) {
                $status = $round['status'];
                /*
                 * 3 结束 1 抽过 -1 未开启 0 可参与
                 */
                if ($status == 0) {
                    return $round['round_num'];
                }
            }
        }
        return 0;
    }


    /**
     * @use 抽奖
     * @param array $lottery_list
     * @return bool
     */
    private static function drawLottery(array $lottery_list): bool
    {
        foreach ($lottery_list as $lottery) {
            $aid = $lottery['aid'];
            $num = $lottery['num'];
            Log::notice("实物抽奖 {$aid} 轮次 {$num} 可参与抽奖~");
            $url = 'https://api.live.bilibili.com/lottery/v1/Box/draw';
            $payload = [
                'aid' => $aid,
                'number' => $num,
            ];
            $raw = Curl::get('pc', $url, $payload);
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] == 0) {
                Log::notice("实物抽奖 {$aid} 轮次 {$num} 参与抽奖成功~");
            } else {
                Log::notice("实物抽奖 {$aid} 轮次 {$num} {$de_raw['msg']}~");
            }
        }
        return true;
    }


    /**
     * @use 计算Aid
     * @param $min
     * @param $max
     * @return bool
     * @throws \Exception
     */
    private static function calcAid($min, $max): bool
    {
        if (self::$end_aid != 0 && self::$start_aid != 0) {
            return false;
        }
        while (true) {
            $middle = round(($min + $max) / 2);
            if (self::boxStatus($middle)) {
                if (self::boxStatus($middle + random_int(0, 3))) {
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
        self::$start_aid = $min - random_int(15, 30);
        self::$end_aid = $min + random_int(15, 30);
        Log::info("实物抽奖起始值[" . self::$start_aid . "]，结束值[" . self::$end_aid . "]");
        return true;
    }
}