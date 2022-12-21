<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\Msg;

use Bhp\Request\Request;
use Bhp\Sign\Sign;
use Bhp\User\User;

class ApiMsg
{
    /**
     * @param int $room_id
     * @param string $content
     * @return array
     */
    public static function sendBarragePC(int $room_id, string $content): array
    {

        $user = User::parseCookie();
        $url = 'https://api.live.bilibili.com/msg/send';
        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $content,
            'rnd' => 0,
            'bubble' => 0,
            'roomid' => $room_id,
            'csrf' => $user['csrf'],
            'csrf_token' => $user['csrf'],
        ];


        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/$room_id"
        ];
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }

    public static function sendBarrageAPP(int $room_id, string $content): array
    {
        $user = User::parseCookie();
        $url = 'https://api.live.bilibili.com/msg/send';
        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $content,
            'rnd' => 0,
            'roomid' => $room_id,
            'csrf' => $user['csrf'],
            'csrf_token' => $user['csrf'],
        ];
        return Request::postJson(true, 'app', $url, Sign::common($payload));
    }
}

