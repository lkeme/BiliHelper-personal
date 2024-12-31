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

namespace Bhp\Api\LinkGroup;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiLoveClub
{
    /**
     * 获取我的友爱社列表
     * @return array
     */
    public static function myGroups(): array
    {
        $url = 'https://api.vc.bilibili.com/link_group/v1/member/my_groups';
        $payload = [];
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }

    /**
     * @param int $group_id 应援团id
     * @param int $owner_id 爱豆ID
     * @return array
     */
    public static function signIn(int $group_id, int $owner_id): array
    {
        $url = 'https://api.vc.bilibili.com/link_setting/v1/link_setting/sign_in';
        $payload = [
            'group_id' => $group_id,
            'owner_id' => $owner_id,
        ];
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }

}
