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

namespace Bhp\Api\Video;

use Bhp\Request\Request;
use Bhp\User\User;

class ApiVideo
{
    /**
     * @param int $pn
     * @param int $ps
     * @return array
     */
    public static function newlist(int $pn, int $ps): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/newlist';
        $payload = [
            'pn' => $pn,
            'ps' => $ps
        ];
        return Request::getJson(true, 'other', $url, $payload);
    }

    /**
     * 获取分区动态/首页推荐
     * @param int $ps
     * @return array
     */
    public static function dynamicRegion(int $ps = 30): array
    {
        // 动画1 国创168 音乐3 舞蹈129 游戏4 知识36 科技188 汽车223 生活160 美食211 动物圈127 鬼畜119 时尚155 资讯202 娱乐5 影视181
        $rids = [1, 168, 3, 129, 4, 36, 188, 223, 160, 211, 127, 119, 155, 202, 5, 181];
        //
        $url = 'https://api.bilibili.com/x/web-interface/dynamic/region';
        $payload = [
            'ps' => $ps,
            'rid' => $rids[array_rand($rids)],
        ];
        return Request::getJson(true, 'other', $url, $payload);
    }

    /**
     * 获取榜单稿件
     * @return array
     */
    public static function ranking(): array
    {
        // day: 日榜1 三榜3 周榜7 月榜30
        $url = 'https://api.bilibili.com/x/web-interface/ranking';
        $payload = [
            'rid' => 0,
            'day' => 1,
            'type' => 1,
            'arc_type' => 0
        ];
        return Request::getJson(true, 'other', $url, $payload);
    }

    /**
     * 首页填充物 max=30
     * @param int $ps
     * @return array
     */
    public static function topFeedRCMD(int $ps = 30): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/wbi/index/top/feed/rcmd';
        $payload = [
            'ps' => $ps,
        ];
        return Request::getJson(true, 'other', $url, $payload);
    }

}
