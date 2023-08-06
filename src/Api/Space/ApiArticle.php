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

namespace Bhp\Api\Space;

use Bhp\Request\Request;

class ApiArticle
{
    /**
     * 获取用户专栏列表
     * @param string $uid
     * @param int $pn 页码
     * @param int $ps 每页数量 1-30
     * @param string $sort publish_time：最新发布 / view：最多阅读 / fav：最多收藏
     * @return array
     */
    public static function article(string $uid, int $pn = 1, int $ps = 2, string $sort = 'publish_time'): array
    {
        $url = 'https://api.bilibili.com/x/space/article';
        $payload = [
            'mid' => $uid,
            'pn' => $pn,
            'ps' => $ps,
            'sort' => $sort,
        ];
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => "https://space.bilibili.com/$uid/"
        ];
        //
        return Request::getJson(true, 'other', $url, $payload, $headers);
    }

}
