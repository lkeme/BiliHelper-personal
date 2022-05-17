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
use BiliHelper\Util\BaseRaffle;

class RedPocketRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '利是包';
    const ACTIVE_SWITCH = 'live_red_pocket';

    protected static array $wait_list = [];
    protected static array $finish_list = [];
    protected static array $all_list = [];

    /**
     * @use 解析数据
     * @param int $room_id
     * @param array $data
     * @return bool
     */
    protected static function parseLotteryInfo(int $room_id, array $data): bool
    {
        return true;
    }

    /**
     * @use 创建抽奖任务
     * @param array $raffles
     * @return array
     */
    protected static function createLottery(array $raffles): array
    {
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/popularityRedPocket/RedPocketDraw';
        $tasks = [];
        foreach ($raffles as $raffle) {
            $payload = [
                'ruid' => $raffle['ruid'],
                'room_id' => $raffle['room_id'],
                'lot_id' => $raffle['lot_id'],
                'spm_id' => '444.8.red_envelope.extract',
                'jump_from' => '',
                'session_id' => '',
                'csrf_token' => getCsrf(),
                'csrf' => getCsrf(),
                'visit_id' => ''
            ];
            // {"code":0,"message":"0","ttl":1,"data":{"join_status":1}}
            $tasks[] = [
                'payload' => Sign::common($payload),
                'source' => [
                    'room_id' => $raffle['room_id'],
                    'raffle_id' => $raffle['raffle_id'],
                    'raffle_name' => $raffle['raffle_name']
                ]
            ];
        }
        // print_r($results);
        return Curl::async('app', $url, $tasks);
    }

    /**
     * @use 解析抽奖信息
     * @param array $results
     * @return string
     */
    protected static function parseLottery(array $results): string
    {
        return '';
    }
}