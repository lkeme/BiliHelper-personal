<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Curl;
use BiliHelper\Core\Log;
use BiliHelper\Tool\Common;
use JetBrains\PhpStorm\ArrayShape;

class User
{
    /**
     * @use 实名检测
     * @return bool
     */
    public static function realNameCheck(): bool
    {
        $url = 'https://account.bilibili.com/identify/index';
        $payload = [];
        $raw = Curl::get('pc', $url, $payload);
        $de_raw = json_decode($raw, true);
        //检查有没有名字，没有则没实名
        if (!$de_raw['data']['memberPerson']['realname']) {
            return false;
        }
        return true;
    }

    /**
     * @use 老爷检测
     * @return bool
     */
    public static function isMaster(): bool
    {
        $data = self::getUserInfo();
        if ($data['msg'] == 'ok') {
            if ($data['data']['vip'] || $data['data']['svip']) {
                return true;
            }
        }
        return false;
    }

    /**
     * @use UserInfo
     * @return array
     */
    public static function getUserInfo(): array
    {
        $url = 'https://api.live.bilibili.com/User/getUserInfo';
        $payload = [
            'ts' => Common::getUnixTimestamp(),
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        return json_decode($raw, true);
    }


    /**
     * @use Web User
     * @param null $room_id
     * @return mixed
     */
    public static function webGetUserInfo($room_id = null): mixed
    {
        $url = 'https://api.live.bilibili.com/xlive/web-room/v1/index/getInfoByUser';
        $payload = [
            'room_id' => $room_id ?? getConf('room_id', 'global_room')
        ];
        $raw = Curl::get('pc', $url, $payload);
        return json_decode($raw, true);
    }


    /**
     * @use App User
     * @param null $room_id
     * @return mixed
     */
    public static function appGetUserInfo($room_id = null): mixed
    {
        $url = 'https://api.live.bilibili.com/xlive/app-room/v1/index/getInfoByUser';
        $payload = [
            'room_id' => $room_id ?? getConf('room_id', 'global_room')
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        return json_decode($raw, true);
    }

    /**
     * @use 转换信息
     * @return array
     */
    #[ArrayShape(['csrf' => "mixed|string", 'uid' => "mixed|string", 'sid' => "mixed|string"])]
    public static function parseCookies(): array
    {
        $cookies = getCookie();
        preg_match('/bili_jct=(.{32})/', $cookies, $token);
        preg_match('/DedeUserID=(\d+)/', $cookies, $uid);
        preg_match('/DedeUserID__ckMd5=(.{16})/', $cookies, $sid);
        return [
            'csrf' => $token[1] ?? '',
            'uid' => $uid[1] ?? '',
            'sid' => $sid[1] ?? '',
        ];
    }

    /**
     * @use 获取全部关注列表
     * @return array
     */
    public static function fetchAllFollowings(): array
    {
        $uid = getUid();
        $followings = [];
        for ($i = 1; $i < 100; $i++) {
            $url = "https://api.bilibili.com/x/relation/followings";
            $payload = [
                'vmid' => $uid,
                'pn' => $i,
                'ps' => 50,
            ];
            $headers = [
                'referer' => "https://space.bilibili.com/$uid/fans/follow?tagid=-1",
            ];
            $raw = Curl::get('pc', $url, $payload, $headers);
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] == 0 && isset($de_raw['data']['list'])) {
                foreach ($de_raw['data']['list'] as $user) {
                    $followings[] = $user['mid'];
                }
                if (count($followings) == $de_raw['data']['total']) {
                    break;
                }
                continue;
            }
            break;
        }
        return $followings;
    }

    /**
     * @use 获取分组关注列表
     * @param int $tag_id
     * @param int $page_num
     * @param int $page_size
     * @return array
     */
    public static function fetchTagFollowings(int $tag_id = 0, int $page_num = 100, int $page_size = 50): array
    {
        $uid = getUid();
        $followings = [];
        for ($i = 1; $i < $page_num; $i++) {
            $url = "https://api.bilibili.com/x/relation/tag";
            $payload = [
                'mid' => $uid,
                'tagid' => $tag_id,
                'pn' => $i,
                'ps' => $page_size,
            ];
            $headers = [
                'referer' => "https://space.bilibili.com/$uid/fans/follow?tagid=$tag_id",
            ];
            $raw = Curl::get('pc', $url, $payload, $headers);
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] == 0 && isset($de_raw['data'])) {
                foreach ($de_raw['data'] as $user) {
                    $followings[] = $user['mid'];
                }
                if (count($de_raw['data']) != $page_size || empty($de_raw['data'])) {
                    break;
                }
                continue;
            }
            break;
        }
        return $followings;
    }

    /**
     * @use 设置用户关注
     * @param int $follow_uid
     * @param bool $un_follow
     * @return bool
     */
    public static function setUserFollow(int $follow_uid, bool $un_follow = false): bool
    {
        $url = 'https://api.live.bilibili.com/relation/v1/Feed/SetUserFollow';
        $payload = [
            'uid' => getUid(),
            'type' => $un_follow ? 0 : 1,
            'follow' => $follow_uid,
            're_src' => 18,
            'csrf_token' => getCsrf(),
            'csrf' => getCsrf(),
            'visit_id' => ''
        ];
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => 'https://live.bilibili.com/',
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        // {"code":0,"msg":"success","message":"success","data":[]}
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * @use 创建关注分组
     * @param string $tag_name
     * @return int
     */
    public static function createRelationTag(string $tag_name): int
    {
        $url = 'https://api.bilibili.com/x/relation/tag/create?cross_domain=true';
        $payload = [
            'tag' => $tag_name,
            'csrf' => getCsrf(),
        ];
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'origin' => 'https://live.bilibili.com',
            'referer' => 'https://link.bilibili.com/iframe/blfe-link-followIframe'
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1,"data":{"tagid":244413}}
        if ($de_raw['code'] == 0 && isset($de_raw['data']['tagid'])) {
            return $de_raw['data']['tagid'];
        }
        return 0;
    }

    /**
     * @use 添加用户到分组
     * @param int $fid
     * @param int $tid
     * @return bool
     */
    public static function tagAddUsers(int $fid, int $tid): bool
    {
        $url = 'https://api.bilibili.com/x/relation/tags/addUsers?cross_domain=true';
        $payload = [
            'fids' => $fid,
            'tagids' => $tid,
            'csrf' => getCsrf(),
        ];
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'origin' => 'https://live.bilibili.com',
            'referer' => 'https://link.bilibili.com/iframe/blfe-link-followIframe'
        ];
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1}
        if ($de_raw['code'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * @use 获取分组列表
     * @return array
     */
    public static function fetchTags(): array
    {
        $tags = [];
        $url = 'https://api.bilibili.com/x/relation/tags';
        $payload = [];
        $headers = [
            'referer' => 'https://space.bilibili.com/' . getUid() . '/fans/follow',
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0 && isset($de_raw['data'])) {
            foreach ($de_raw['data'] as $tag) {
                $tags[$tag['tagid']] = $tag['name'];
            }
        }
        return $tags;
    }

    /**
     * @use 是否为有效年度大会员
     * @return bool
     */
    public static function isYearVip(): bool
    {
        $url = 'https://api.bilibili.com/x/vip/web/user/info';
        $headers = [
            'origin' => 'https://account.bilibili.com',
            'referer' => 'https://account.bilibili.com/account/home'
        ];
        $payload = [];
        $raw = Curl::get('pc', $url, $payload, $headers);
        // {"code":0,"message":"0","ttl":1,"data":{"mid":1234,"vip_type":2,"vip_status":1,"vip_due_date":1667750400000,"vip_pay_type":0,"theme_type":0,"label":{"text":"年度大会员","label_theme":"annual_vip","text_color":"#FFFFFF","bg_style":1,"bg_color":"#FB7299","border_color":""},"avatar_subscript":1,"avatar_subscript_url":"http://i0.hdslb.com/bfs/vip/icon_Certification_big_member_22_3x.png","nickname_color":"#FB7299","is_new_user":false}}
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            if ($de_raw['data']['vip_type'] == 2 && $de_raw['data']['vip_due_date'] > Common::getUnixTimestamp()) {
                Log::debug("获取会员成功 有效年度大会员");
                return true;
            }
            Log::debug("获取会员成功 不是年度大会员或已过期");
        } else {
            Log::debug("获取会员信息失败 $raw");
        }
        return false;
    }

    /**
     * @use 我的钱包
     */
    public static function myWallet(): void
    {
        $url = 'https://api.live.bilibili.com/pay/v2/Pay/myWallet';
        $headers = [
            'origin' => 'https://link.bilibili.com',
            'referer' => 'https://link.bilibili.com/p/center/index'
        ];
        $payload = [
            'need_bp' => 1,
            'need_metal' => 1,
            'platform' => 'pc',
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        // {"code":0,"msg":"succ","message":"succ","data":{"gold":5074,"silver":37434,"bp":"0","metal":1904}}
        $de_raw = json_decode($raw, true);
    }

}