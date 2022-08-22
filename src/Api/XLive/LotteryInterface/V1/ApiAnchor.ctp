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

namespace Bhp\Api\XLive\LotteryInterface\V1;

use Bhp\Request\Request;

class ApiAnchor
{
    /**
     * 天选时刻
     * @return array
     */
    public static function awardRecord(): array
    {
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/Anchor/AwardRecord';
        $payload = [
            'page' => 1,
        ];
        $headers = [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index'
        ];
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }
}
