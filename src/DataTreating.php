<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class DataTreating
{
    /**
     * @desc 抽奖分发
     * @param array $data
     */
    public static function distribute(array $data)
    {
        // var_dump($data);
        // room_id raffle_id raffle_title raffle_type
        try {
            $info = ['rid' => $data['room_id'], 'lid' => $data['raffle_id']];
        } catch (\Exception $e) {
            return;
        }
        switch ($data['raffle_type']) {
            case "storm":
                // 风暴
                Storm::pushToQueue($info);
                break;
            case "raffle":
                // 礼物
                UnifyRaffle::pushToQueue($info);
                break;
            case "guard":
                // 舰长
                Guard::pushToQueue($info);
                break;
            case "small_tv":
                // 电视
                UnifyRaffle::pushToQueue($info);
                break;
            case 'pk':
                // 乱斗
                PkRaffle::pushToQueue($info);
                break;
            default:
                break;
        }
    }
}
