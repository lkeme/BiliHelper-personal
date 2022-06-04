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

namespace Bhp\Api\LinkGroup;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiLoveClub
{
    /**
     * @use 获取我的友爱社列表
     * @return array
     */
    public static function myGroups(): array
    {
        $url = 'https://api.vc.bilibili.com/link_group/v1/member/my_groups';
        $payload = [];
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }

    /**
     * @param string $group_id 应援团id
     * @param string $owner_id 爱豆ID
     * @return array
     */
    public static function signIn(string $group_id, string $owner_id): array
    {
        $url = 'https://api.vc.bilibili.com/link_setting/v1/link_setting/sign_in';
        $payload = [
            'group_id' => $group_id,
            'owner_id' => $owner_id,
        ];
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }


}