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

namespace Bhp\Api\XLive\DataInterface\V1\HeartBeat;

use Bhp\Api\Custom\ApiCalcSign;
use Bhp\Config\Config;
use Bhp\Log\Log;
use Bhp\Request\Request;
use Bhp\Sign\Sign;
use Bhp\User\User;
use Bhp\Util\Common\Common;

class ApiHeartBeat
{
    /**
     * 心跳
     * @param int $room_id
     * @param int $up_id
     * @param int $parent_id
     * @param int $area_id
     * @param string $client_sign
     * @return array
     */
    public static function mobileHeartBeat(int $room_id, int $up_id, int $parent_id, int $area_id, string $client_sign): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://live-trace.bilibili.com/xlive/data-interface/v1/heartbeat/mobileHeartBeat';
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $payload = [
            'platform' => 'android',
            'uuid' => Common::customCreateUUID((string)$user['uid']),
            'buvid' => Request::getBuvid(),
            'seq_id' => '1',
            'room_id' => $room_id,
            'parent_id' => $parent_id,
            'area_id' => $area_id,
            'timestamp' => time() - 60,
            'secret_key' => 'axoaadsffcazxksectbbb',
            'watch_time' => '60',
            'up_id' => $up_id,
            'up_level' => '40',
            'jump_from' => '30000',
            'gu_id' => strtoupper(Common::randString(43)),
            'play_type' => '0',
            'play_url' => '',
            's_time' => '0',
            'data_behavior_id' => '',
            'data_source_id' => '',
            'up_session' => "l:one:live:record:$room_id:" . time() - 88888,
            'visit_id' => strtoupper(Common::randString(32)),
            'watch_status' => '%7B%22pk_id%22%3A0%2C%22screen_status%22%3A1%7D',
            'click_id' => Common::customCreateUUID(strrev((string)$user['uid'])),
            'session_id' => '',
            'player_type' => '0',
            'client_ts' => time(),
        ];
        //
        $payload['client_sign'] = self::calcClientSign($payload);
        //
        return Request::postJson(true, 'app', $url, Sign::common($payload), $headers);
    }

    /**
     * 计算 TODO 调整位置
     * @param array $t
     * @return string
     */
    protected static function calcClientSign(array $t): string
    {
        $url = getConf('heartbeat.app');
        $r = [3, 7, 2, 6, 8];
        $response = ApiCalcSign::heartBeat($url, $t, $r);
        if ($response['code'] != 0) {
            Log::warning("心跳加密错误: {$response['code']}->{$response['message']}");
        }
        return $response['s'];
    }
}
