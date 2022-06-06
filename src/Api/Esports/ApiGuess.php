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

namespace Bhp\Api\Esports;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiGuess
{
    /**
     * @use 获取赛事竞猜
     * @param int $pn
     * @param int $ps
     * @return array
     */
    public static function collectionQuestion(int $pn, int $ps = 50): array
    {
        $url = 'https://api.bilibili.com/x/esports/guess/collection/question';
        $payload = [
            'pn' => $pn,
            'ps' => $ps,
            'stime' => date("Y-m-d H:i:s", strtotime(date("Y-m-d", time()))),
            'etime' => date("Y-m-d H:i:s", strtotime(date("Y-m-d", time())) + 86400 - 1)
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/v/game/match/competition',
        ];
        return Request::getjSON(true, 'pc', $url, $payload, $headers);
    }

    /**
     * @use 竞猜
     * @param int $oid
     * @param int $main_id
     * @param int $detail_id
     * @param int $count
     * @return array
     */
    public static function guessAdd(int $oid, int $main_id, int $detail_id, int $count): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/esports/guess/add';
        $payload = [
            'oid' => $oid,
            'main_id' => $main_id,
            'detail_id' => $detail_id,
            'count' => $count,
            'is_fav' => 0,
            'csrf' => $user['csrf'],
            'csrf_token' => $user['csrf'],
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/v/game/match/competition'
        ];
        return Request::postJson(true, 'pc', $url, $payload, $headers);
    }


}
 