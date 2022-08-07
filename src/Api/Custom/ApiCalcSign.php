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

namespace Bhp\Api\Custom;

use Bhp\Request\Request;
use Bhp\User\User;
use JetBrains\PhpStorm\ArrayShape;

class ApiCalcSign
{

    protected static function formatT(array $t): array
    {
        return $t;
//        return [
//            'id' => $t['id'],
//            'device' => $t['device'],
//            'ets' => $t['ets'],
//            'benchmark' => $t['benchmark'],
//            'time' => $t['time'],
//            'ts' => $t['ts'],
//            'ua' => $t['ua'],
//        ];
    }


    protected static function formatR(array $r): array
    {
        return $r;
    }


    /**
     * 获取关注Up动态
     * @return mixed
     */
    public static function heartBeat(string $url, array $t, array $r): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];
        // 加密部分
        $payload = [
            't' => static::formatT($t),
            'r' => static::formatR($r)
        ];
        return Request::putJson(true, 'other', $url, $payload, $headers);
    }

}

