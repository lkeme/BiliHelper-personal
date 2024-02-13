<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2024 ~ 2025
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Room\V1;

use Bhp\Request\Request;

class ApiInfo
{

    /**
     * 获取直播间信息
     * @param $room_id
     * @return array
     */
    public static function getRoomInfoV1($room_id): array
    {
        $url = 'https://api.live.bilibili.com/room/v1/Room/room_init';
        $payload = [
            'id' => $room_id
        ];
        return Request::getJson(true, 'other', $url, $payload);
    }

    /**
     *  获取直播间信息
     * @param $room_id
     * @return array
     */
    public static function getRoomInfoV2($room_id): array
    {
        $url = ' https://api.live.bilibili.com/room/v1/Room/get_info_by_id';
        $payload = [
            'ids[]' => $room_id
        ];
        return Request::getJson(true, 'other', $url, $payload);
    }

}
