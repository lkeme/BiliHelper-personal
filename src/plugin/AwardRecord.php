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

class AwardRecord
{
    use TimeLock;

    private static int $raffle_lock = 0;
    private static array $raffle_list = [];
    private static int $anchor_lock = 0;
    private static array $anchor_list = [];
    private static int $gift_lock = 0;
    private static array $gift_list = [];

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('award_record')) {
            return;
        }
        if (self::$anchor_lock < time()) {
            self::anchorAward();
        }
        if (self::$raffle_lock < time()) {
            self::raffleAward();
        }
        // if (self::$gift_lock < time()) {
        //     self::giftAward();
        // }
        self::setLock(5 * 60);
    }

    /**
     * @use 获取天选时刻中奖纪录
     */
    private static function anchorAward(): void
    {
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/Anchor/AwardRecord';
        $payload = [
            'page' => '1',
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);
        // 防止异常
        if (!isset($de_raw['data']) || !isset($de_raw['data']['list'])) {
            Log::warning("获取天选时刻获奖记录错误: " . json_encode($de_raw, JSON_FORCE_OBJECT));
            self::$anchor_lock = time() + 60 * 60;
            return;
        }
        foreach ($de_raw['data']['list'] as $anchor) {
            $win_time = strtotime($anchor['end_time']);  //礼物时间
            $day = ceil((time() - $win_time) / 86400);  //60s*60min*24h
            // 去重
            if (in_array($anchor['id'], self::$anchor_list)) {
                continue;
            }
            // 范围
            if ($day <= 2) {
                $info = $anchor['award_name'] . 'x' . $anchor['award_num'];
                Log::notice("天选时刻于" . $anchor['end_time'] . "获奖: $info ,请留意查看...");
                Notice::push('anchor', $info);
            }
            self::$anchor_list[] = $anchor['id'];
        }
        // 处理取关操作
        foreach (AnchorRaffle::$wait_un_follows as $wait_un_follow) {
            if ($wait_un_follow['time'] > time()) {
                continue;
            }
            if (in_array($wait_un_follow['anchor_id'], self::$anchor_list)) {
                AnchorRaffle::delToGroup($wait_un_follow['uid'], $wait_un_follow['anchor_id'], false);
            } else {
                AnchorRaffle::delToGroup($wait_un_follow['uid'], $wait_un_follow['anchor_id']);
            }
        }

        self::$anchor_lock = time() + 10 * 60;
    }

    /**
     * @use 获取实物抽奖中奖纪录
     */
    private static function raffleAward(): void
    {
        $url = 'https://api.live.bilibili.com/lottery/v1/award/award_list';
        $payload = [
            'page' => '1',
            'month' => '',
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);

        // 防止异常
        if (!isset($de_raw['data']) || !isset($de_raw['data']['list']) || $de_raw['code']) {
            Log::warning("获取实物奖励获奖记录错误: " . $de_raw['msg']);
            self::$raffle_lock = time() + 60 * 60;
            return;
        }
        foreach ($de_raw['data']['list'] as $raffle) {
            $win_time = strtotime($raffle['create_time']);  //礼物时间
            $day = ceil((time() - $win_time) / 86400);  //60s*60min*24h
            // 去重
            if (in_array($raffle['id'], self::$raffle_list)) {
                continue;
            }
            // 范围
            if ($day <= 2 && empty($raffle['update_time'])) {
                $info = $raffle['gift_name'] . 'x' . $raffle['gift_num'];
                Log::notice("实物奖励于" . $raffle['create_time'] . "获奖: $info ,请留意查看...");
                Notice::push('raffle', $info);
            }
            self::$raffle_list[] = $raffle['id'];
        }
        self::$raffle_lock = time() + 6 * 60 * 60;
    }

    /**
     * @use 获取活动礼物中奖纪录
     */
    private static function giftAward(): void
    {
        // Web V3 Notice
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v3/smalltv/Notice';
        $payload = [
            'type' => 'type',
            'raffleId' => 'raffle_id'
        ];
        // 请求 && 解码
        $raw = Curl::get('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);
    }
}