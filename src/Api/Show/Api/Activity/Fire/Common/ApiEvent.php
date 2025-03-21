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


namespace Bhp\Api\Show\Api\Activity\Fire\Common;

use Bhp\Request\Request;
use Bhp\Sign\Sign;
use Bhp\User\User;

class ApiEvent
{
    /**
     * @var array|string[]
     */
    protected static array $headers = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task'
    ];

    /**
     * @return array
     */
    public static function dispatch(): array
    {
        //
        $user = User::parseCookie();
        //
        $params = [
            'csrf' => $user['csrf'],
            'statistics' => getDevice('app.bili_a.statistics'),
        ];
        $url = 'https://show.bilibili.com/api/activity/fire/common/event/dispatch?' . http_build_query(Sign::common($params));
        //
        $payload = [
            'eventId' => 'hevent_oy4b7h3epeb',
        ];
        //
        $headers = array_merge([
            'content-type' => 'application/json; charset=utf-8',
        ], self::$headers);
        //
        return Request::putJson(true, 'app', $url, $payload, $headers);
    }

}
