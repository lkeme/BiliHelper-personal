<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 *  LastAPIChecked: null
 */

namespace lkeme\BiliHelper;

class MaterialObject
{
    // 时间锁
    public static $lock = 0;
    // 丢弃列表
    private static $discard_aid_list = [];
    // 起始 和 结束
    private static $start_aid = 0;
    private static $end_aid = 0;

    // RUN
    public static function run()
    {
        if (getenv('USE_MO') == 'false') {
            return;
        }
        if (self::$lock > time()) {
            return;
        }
        // 计算AID TODO 待优化
        self::calculateAid(150, 550);
        self::drawLottery();

        self::$lock = time() + random_int(5, 10) * 60;
    }

    /**
     * @use 实物抽奖
     * @return bool
     */
    protected static function drawLottery(): bool
    {
        $block_key_list = ['测试', '加密', 'test', 'TEST', '钓鱼', '炸鱼', '调试'];
        $flag = 5;
        
        for ($i = self::$start_aid; $i < self::$end_aid; $i++) {
            if (!$flag) {
                break;
            }
            // 在丢弃列表里 跳过
            if (in_array($i, self::$discard_aid_list)) {
                continue;
            }

            $payload = [
                'aid' => $i,
            ];
            $url = 'https://api.live.bilibili.com/lottery/v1/box/getStatus';
            // 请求 && 解码
            $raw = Curl::get($url, Sign::api($payload));
            $de_raw = json_decode($raw, true);
            // -403 没有抽奖
            if ($de_raw['code'] != '0') {
                $flag--;
                continue;
            }
            // 如果最后一个结束时间已过 加入丢弃
            $lotterys = $de_raw['data']['typeB'];
            $total = count($lotterys);
            if ($lotterys[$total - 1]['join_end_time'] < time()) {
                array_push(self::$discard_aid_list, $i);
                continue;
            }

            // 如果存在敏感词 加入丢弃
            $title = $de_raw['data']['title'];
            foreach ($block_key_list as $block_key) {
                if (strpos($title, $block_key) !== false) {
                    array_push(self::$discard_aid_list, $i);
                    continue;
                }
            }

            $num = 1;
            foreach ($lotterys as $lottery) {
                $join_end_time = $lottery['join_end_time'];
                $join_start_time = $lottery['join_start_time'];

                if ($join_end_time > time() && time() > $join_start_time) {
                    switch ($lottery['status']) {
                        case 3:
                            Log::info("实物[{$i}]抽奖: 当前轮次已经结束!");
                            break;
                        case 1:
                            Log::info("实物[{$i}]抽奖: 当前轮次已经抽过了!");
                            break;
                        case -1:
                            Log::info("实物[{$i}]抽奖: 当前轮次暂未开启!");
                            break;
                        case 0:
                            Log::info("实物[{$i}]抽奖: 当前轮次正在抽奖中!");

                            $payload = [
                                'aid' => $i,
                                'number' => $num,
                            ];
                            $raw = Curl::get('https://api.live.bilibili.com/lottery/v1/box/draw', Sign::api($payload));
                            $de_raw = json_decode($raw, true);

                            if ($de_raw['code'] == 0) {
                                Log::notice("实物[{$i}]抽奖: 成功!");
                            }
                            $num++;
                            break;

                        default:
                            Log::info("实物[{$i}]抽奖: 当前轮次状态码[{$lottery['status'] }]未知!");
                            break;
                    }
                }
            }
        }
        return true;
    }

    /**
     * @use 计算 开始结束的AID
     * @param $min
     * @param $max
     * @return bool
     */
    private static function calculateAid($min, $max): bool
    {
        if (self::$end_aid != 0 && self::$start_aid != 0) {
            return false;
        }

        while (1) {
            $middle = round(($min + $max) / 2);
            if (self::aidPost($middle)) {
                if (self::aidPost($middle + random_int(0, 3))) {
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

        self::$start_aid = $min - random_int(30, 40);
        self::$end_aid = $min + random_int(30, 40);
        Log::info("实物抽奖起始值[" . self::$start_aid . "]，结束值[" . self::$end_aid . "]");
        return true;
    }

    /**
     * @use Aid 请求
     * @param $aid
     * @return bool
     */
    private static function aidPost($aid): bool
    {
        $payload = [
            'aid' => $aid,
        ];
        $raw = Curl::get('https://api.live.bilibili.com/lottery/v1/box/getStatus', Sign::api($payload));
        $de_raw = json_decode($raw, true);

        // 等于0是有抽奖返回false
        if ($de_raw['code'] == 0) {
            return false;
        }
        // 没有抽奖
        return true;
    }
}