<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use Exception;

class DataTreating
{
    // Todo 独立分发 Push||Pull数据
    /**
     * @use 抽奖分发
     * @param array $data
     */
    public static function distribute(array $data): void
    {
        // var_dump($data);
        // room_id raffle_id raffle_title raffle_type
        try {
            $info = ['rid' => $data['room_id'], 'lid' => $data['raffle_id']];
        } catch (Exception $e) {
            return;
        }
        switch ($data['raffle_type']) {
            case 'storm':
                // 风暴
                StormRaffle::pushToQueue($info);
                break;
            case 'raffle':
                // 礼物
                GiftRaffle::pushToQueue($info);
                break;
            case 'guard':
                // 舰长
                GuardRaffle::pushToQueue($info);
                break;
            case 'small_tv':
                // 电视
                GiftRaffle::pushToQueue($info);
                break;
            case 'pk':
                // 乱斗
                PkRaffle::pushToQueue($info);
                break;
            case 'anchor':
                // 天选时刻
                AnchorRaffle::pushToQueue($info);
                break;
            case 'red_pocket':
                // 利是包
                RedPocketRaffle::pushToQueue($info);
                break;
            default:
                break;
        }
    }
}
