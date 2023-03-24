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

namespace Bhp\Api\Room\V1;

use Bhp\Request\Request;

class ApiDanMu
{
    /**
     * web端获取直播间信息
     * @param int $room_id
     * @return array
     */
    public static function getConf(int $room_id): array
    {
        $url = 'https://api.live.bilibili.com/room/v1/Danmu/getConf';
        $payload = [
            'room_id' => $room_id,
            'platform' => 'pc',
            'player' => 'web'
        ];
        // {"code":0,"msg":"ok","message":"ok","data":{"refresh_row_factor":0.125,"refresh_rate":100,"max_delay":5000,"port":2243,"host":"broadcastlv.chat.bilibili.com","host_server_list":[{"host":"ks-live-dmcmt-sh2-pm-03.chat.bilibili.com","port":2243,"wss_port":443,"ws_port":2244},{"host":"ks-live-dmcmt-bj6-pm-02.chat.bilibili.com","port":2243,"wss_port":443,"ws_port":2244},{"host":"broadcastlv.chat.bilibili.com","port":2243,"wss_port":443,"ws_port":2244}],"server_list":[{"host":"120.92.158.137","port":2243},{"host":"120.92.112.150","port":2243},{"host":"broadcastlv.chat.bilibili.com","port":2243},{"host":"120.92.158.137","port":80},{"host":"120.92.112.150","port":80},{"host":"broadcastlv.chat.bilibili.com","port":80}],"token":"*="}}
        return Request::getJson(true, 'other', $url, $payload);
    }

}
