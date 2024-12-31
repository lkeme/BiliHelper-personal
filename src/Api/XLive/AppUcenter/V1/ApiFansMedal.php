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

namespace Bhp\Api\XLive\AppUcenter\V1;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiFansMedal
{
    /**
     * 粉丝勋章面板
     * @param int $pn
     * @param int $ps
     * @return array
     */
    public static function panel(int $pn, int $ps): array
    {
        // https://live.bilibili.com/p/html/live-app-fansmedal-manange/index.html
        $url = 'https://api.live.bilibili.com/xlive/app-ucenter/v1/fansMedal/panel';
        $payload = [
            'page' => $pn,
            'page_size' => $ps,
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"list":[],"special_list":[],"bottom_bar":null,"page_info":{"number":0,"current_page":1,"has_more":false,"next_page":2,"next_light_status":2},"total_number":0,"has_medal":0}}
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }


}
