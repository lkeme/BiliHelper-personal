<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\XLive\AppRoom\V1;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiDM
{

    /**
     * 发送弹幕(APP)
     * @param int $room_id
     * @param string $msg
     * @return array
     */
    public static function sendMsg(int $room_id, string $msg): array
    {
        $params = [
            'aid' => '',
            'page' => '1',
            //'statistics' => getDevice('app.bili_a.statistics'),
        ];
        $url = 'https://api.live.bilibili.com/xlive/app-room/v1/dM/sendmsg?' . http_build_query(Sign::common($params));
        $payload = [
            "cid" => $room_id,
            "msg" => $msg,
            "rnd" => time(),
            "color" => "16777215",
            "fontsize" => "25",
        ];
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];

        // {"code":0,"data":{"mode_info":{"extra":"{\"send_from_me\":true,\"mode\":0,\"color\":16777215,\"dm_type\":0,\"font_size\":25,\"player_mode\":1,\"show_player_type\":0,\"content\":\"1\",\"user_hash\":\"111111\",\"emoticon_unique\":\"\",\"bulge_display\":0,\"recommend_score\":8,\"main_state_dm_color\":\"\",\"objective_state_dm_color\":\"\",\"direction\":0,\"pk_direction\":0,\"quartet_direction\":0,\"anniversary_crowd\":0,\"yeah_space_type\":\"\",\"yeah_space_url\":\"\",\"jump_to_url\":\"\",\"space_type\":\"\",\"space_url\":\"\"}","mode":0,"show_player_type":0}},"message":"","msg":""}
        return Request::postJson(true, 'app', $url, $payload, $headers);
    }
}


