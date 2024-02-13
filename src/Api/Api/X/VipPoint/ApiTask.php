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

namespace Bhp\Api\Api\X\VipPoint;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiTask
{
    /**
     * @var array|string[]
     */
    protected static array $headers = [
        'Referer' => 'https://big.bilibili.com/mobile/bigPoint/task'
    ];

    /**
     * 用户信息
     * @return array
     */
    public static function combine(): array
    {
        $url = 'https://api.bilibili.com/x/vip_point/task/combine';
        $payload = [];
        $headers = array_merge([], self::$headers);
        return Request::getJson(true, 'app', $url, Sign::common($payload), $headers);
    }
}
