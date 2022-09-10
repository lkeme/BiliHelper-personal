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
     * @var array|string[]
     */
    protected static array $payload = [
        'statistics' => '{"appId":1,"platform":3,"version":"6.86.0","abtest":""}',
    ];

    /**
     * @return array
     */
    public static function dispatch(): array
    {
        //
        $user = User::parseCookie();
        //
        $url = 'https://show.bilibili.com/api/activity/fire/common/event/dispatch';
        $payload = array_merge([
            'msource' => 'member_integral_browse',
            'action' => 'browse_all',
            'eventId' => 'hevent_oy4b7h3epeb',
            'eventTime' => '10',
            'csrf' => $user['csrf'],
        ], self::$payload);
        $headers = array_merge([
            'content-type'=> 'application/json; charset=utf-8',
        ], self::$headers);
        return Request::postJson(true, 'app', $url, Sign::common($payload), $headers);
    }

}
