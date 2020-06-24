<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Curl;
use BiliHelper\Core\Config;

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
     * @use 写入用户名
     * @return bool
     */
    public static function userInfo(): bool
    {
        $data = self::getUserInfo();
        if (getenv('APP_UNAME') != "") {
            return true;
        }
        if ($data['msg'] == 'ok') {
            Config::put('APP_UNAME', $data['data']['uname']);
            return true;
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
            'ts' => Live::getMillisecond(),
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        return json_decode($raw, true);
    }


    /**
     * @use Web User
     * @param null $room_id
     * @return mixed
     */
    public static function webGetUserInfo($room_id = null)
    {
        $url = 'https://api.live.bilibili.com/xlive/web-room/v1/index/getInfoByUser';
        $payload = [
            'room_id' => $room_id ?? getenv('ROOM_ID')
        ];
        $raw = Curl::get('pc', $url, $payload);
        return json_decode($raw, true);
    }


    /**
     * @use App User
     * @param null $room_id
     * @return mixed
     */
    public static function appGetUserInfo($room_id = null)
    {
        $url = 'https://api.live.bilibili.com/xlive/app-room/v1/index/getInfoByUser';
        $payload = [
            'room_id' => $room_id ?? getenv('ROOM_ID')
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        return json_decode($raw, true);;
    }

    /**
     * @use 转换信息
     * @return array
     */
    public static function parseCookies(): array
    {
        $cookies = getenv('COOKIE');
        preg_match('/bili_jct=(.{32})/', $cookies, $token);
        $token = isset($token[1]) ? $token[1] : '';
        preg_match('/DedeUserID=(\d+)/', $cookies, $uid);
        $uid = isset($uid[1]) ? $uid[1] : '';
        preg_match('/DedeUserID__ckMd5=(.{16})/', $cookies, $sid);
        $sid = isset($sid[1]) ? $sid[1] : '';
        return [
            'token' => $token,
            'uid' => $uid,
            'sid' => $sid,
        ];
    }

    /**
     * @use 获取全部关注列表
     * @return array
     */
    public static function fetchAllFollowings(): array
    {
        $user_info = User::parseCookies();
        $uid = $user_info['uid'];
        $followings = [];
        for ($i = 1; $i < 100; $i++) {
            $url = "https://api.bilibili.com/x/relation/followings";
            $payload = [
                'vmid' => $uid,
                'pn' => $i,
                'ps' => 50,
            ];
            $headers = [
                'referer' => "https://space.bilibili.com/{$uid}/fans/follow?tagid=-1",
            ];
            $raw = Curl::get('pc', $url, $payload, $headers);
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] == 0 && isset($de_raw['data']['list'])) {
                foreach ($de_raw['data']['list'] as $user) {
                    array_push($followings, $user['mid']);
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
        $user_info = User::parseCookies();
        $uid = $user_info['uid'];
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
                'referer' => "https://space.bilibili.com/{$uid}/fans/follow?tagid={$tag_id}",
            ];
            $raw = Curl::get('pc', $url, $payload, $headers);
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] == 0 && isset($de_raw['data'])) {
                foreach ($de_raw['data'] as $user) {
                    array_push($followings, $user['mid']);
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
    public static function setUserFollow(int $follow_uid, $un_follow = false): bool
    {
        $user_info = User::parseCookies();
        $url = 'https://api.live.bilibili.com/relation/v1/Feed/SetUserFollow';
        $payload = [
            'uid' => $user_info['uid'],
            'type' => $un_follow ? 0 : 1,
            'follow' => $follow_uid,
            're_src' => 18,
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
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
        $user_info = User::parseCookies();
        $url = 'https://api.bilibili.com/x/relation/tag/create?cross_domain=true';
        $payload = [
            'tag' => $tag_name,
            'csrf' => $user_info['token'],
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
        $user_info = User::parseCookies();
        $url = 'https://api.bilibili.com/x/relation/tags/addUsers?cross_domain=true';
        $payload = [
            'fids' => $fid,
            'tagids' => $tid,
            'csrf' => $user_info['token'],
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
        $user_info = User::parseCookies();
        $tags = [];
        $url = 'https://api.bilibili.com/x/relation/tags';
        $payload = [];
        $headers = [
            'referer' => "https://space.bilibili.com/{$user_info['uid']}/fans/follow",
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

}