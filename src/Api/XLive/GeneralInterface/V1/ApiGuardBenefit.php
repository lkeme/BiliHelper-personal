<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Api\XLive\GeneralInterface\V1;

use Bhp\Request\Request;

class ApiGuardBenefit
{
    /**
     * 航海回馈
     * @return array
     */
    public static function winListByUser(): array
    {
        $url = 'https://api.live.bilibili.com/xlive/general-interface/v1/guardBenefit/WinListByUser';
        $payload = [
            'page' => 1,
        ];
        $headers = [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index'
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"count":1,"page_count":1,"list":[{"win_id":16211,"recipient_name":"","phone":"","address":"","info_type":1,"award_name":"【韩小沐】月度舰长福利","ruid":642922,"anchor_name":"韩小沐","settlement_time":"2022-08-07 20:57:29"}]}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }
}
