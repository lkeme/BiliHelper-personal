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

namespace Bhp\Api\Vip;

use Bhp\Request\Request;
use Bhp\User\User;
use Bhp\WbiSign\WbiSign;

class ApiUser
{
    /**
     * 用户VIP信息
     * @return array
     */
    public static function userVipInfo(): array
    {
        $url = 'https://api.bilibili.com/x/vip/web/user/info';
        $payload = [];
        $headers = [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/home'
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"mid":1234,"vip_type":2,"vip_status":1,"vip_due_date":1667750400000,"vip_pay_type":0,"theme_type":0,"label":{"text":"年度大会员","label_theme":"annual_vip","text_color":"#FFFFFF","bg_style":1,"bg_color":"#FB7299","border_color":""},"avatar_subscript":1,"avatar_subscript_url":"http://i0.hdslb.com/bfs/vip/icon_Certification_big_member_22_3x.png","nickname_color":"#FB7299","is_new_user":false}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 用户NAV信息
     * @return array
     */
    public static function userNavInfo(): array
    {
        $user = User::parseCookie();
        //
        $url = 'https://api.bilibili.com/x/web-interface/nav';
        $payload = [];
        $headers = [
            'origin' => 'https://space.bilibili.com',
            'referer' => 'https://space.bilibili.com/' . $user['uid']
        ];
        // {"code":-101,"message":"账号未登录","ttl":1,"data":{"isLogin":false,"wbi_img":{"img_url":"https://i0.hdslb.com/bfs/wbi/d2f367bf78934216b7fc14b6e80bb705.png","sub_url":"https://i0.hdslb.com/bfs/wbi/91246ef1d9a6446e9665517705c08269.png"}}}
        return Request::getJson(true, 'pc', $url, $payload, $headers);
    }

    /**
     * 用户SPACE信息
     * @param int $uid
     * @return array
     */
    public static function userSpaceInfo(int $uid = 0): array
    {
        if ($uid == 0) {
            $user = User::parseCookie();
            $uid = $user['uid'];
        }
        //
        $url = 'https://api.bilibili.com/x/space/wbi/acc/info';
        //
        $payload = [
            'mid' => $uid,
            'platform' => 'web',
            // 'wts'=>time(),
            // 'w_rid'=>'',
        ];
        $headers = [
            'origin' => 'https://www.bilibili.com',
            'referer' => 'https://www.bilibili.com/',
        ];
        return Request::getJson(true, 'pc', $url, WbiSign::encryption($payload), $headers);
    }


}
