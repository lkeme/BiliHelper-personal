<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\XLive\AppUcenter\V1;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiUserTask
{
    /**
     * 获取任务进度
     * @param int $up_id
     * @return array
     */
    public static function getUserTaskProgress(int $up_id): array
    {
        $url = 'https://api.live.bilibili.com/xlive/app-ucenter/v1/userTask/GetUserTaskProgress';
        $payload = [
            'target_id' => $up_id,
            'statistics' => getDevice('app.bili_a.statistics'),
        ];

        // 已领取 {"code":0,"message":"0","ttl":1,"data":{"is_surplus":1,"status":3,"progress":5,"target":5,"wallet":{"gold":100,"silver":130},"linked_actions_progress":null}}
        // 可领取 {"code":0,"message":"0","ttl":1,"data":{"is_surplus":1,"status":2,"progress":5,"target":5,"wallet":{"gold":0,"silver":130},"linked_actions_progress":null}}
        // 进行中 {"code":0,"message":"0","ttl":1,"data":{"is_surplus":1,"status":1,"progress":4,"target":5,"wallet":{"gold":0,"silver":130},"linked_actions_progress":null}}
        // 未开始 {"code":0,"message":"0","ttl":1,"data":{"is_surplus":1,"status":0,"progress":0,"target":5,"wallet":{"gold":0,"silver":130},"linked_actions_progress":null}}
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }


    /**
     * 获取任务奖品
     * @param int $up_id
     * @return array
     */
    public static function userTaskReceiveRewards(int $up_id): array
    {
        $url = 'https://api.live.bilibili.com/xlive/app-ucenter/v1/userTask/UserTaskReceiveRewards';
        $payload = [
            'target_id' => $up_id,
            'statistics' => getDevice('app.bili_a.statistics'),
        ];

        // {"code":0,"message":"0","ttl":1,"data":{"num":1}}
        return Request::postJson(true, 'app', $url, Sign::common($payload));
    }

}

