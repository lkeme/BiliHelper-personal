<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;

class Danmu
{
    public static $lock = 0;

    public static function run()
    {
        if (self::$lock > time() || getenv('USE_DANMU') == 'false') {
            return;
        }
        $room_id = empty(getenv('DANMU_ROOMID')) ? Live::getUserRecommend() : Live::getRealRoomID(getenv('DANMU_ROOMID'));
        $msg = empty(getenv('DANMU_CONTENT')) ? self::getMsgInfo() : getenv('DANMU_CONTENT');

        $info = [
            'roomid' => $room_id,
            'content' => $msg,
        ];

        if (self::privateSendMsg($info)) {
            self::$lock = time() + 3600;
            return;
        }

        self::$lock = time() + 30;
    }

    // 获取随机弹幕
    private static function getMsgInfo()
    {
        /**
         *  整理一部分API，收集于网络，侵权麻烦联系我删除.
         *  如果设置项不能用可以选择，只保证代码发布时正常.
         *  格式全部为TEXT，可以自己替换.
         */
        $punctuations = ['，', ',', '。', '!', '.', ';', '——'];
        $apis = [
            'https://api.lwl12.com/hitokoto/v1?encode=realjso',
            'https://api.ly522.com/yan.php?format=text',
            'https://v1.hitokoto.cn/?encode=text',
            'https://api.jysafe.cn/yy/',
            'https://m.mom1.cn/api/yan/api.php',
            'https://api.ooopn.com/yan/api.php?type=text',
            'https://api.imjad.cn/hitokoto/',
            'https://www.ly522.com/hitokoto/',
            'https://www.tddiao.online/word/',
            'https://api.guoch.xyz/',
            'http://www.ooomg.cn/dutang/',
            'https://api.gushi.ci/rensheng.txt',
            'https://api.itswincer.com/hitokoto/v2/',
            'http://api.dsecret.com/yiyan/',
            'https://api.xygeng.cn/dailywd/api/api.php',
        ];
        shuffle($apis);
        try {
            foreach ($apis as $url) {
                $data = Curl::singleRequest('get', $url);
                if (is_null($data)) continue;
                foreach ($punctuations as $punctuation) {
                    if (strpos($data, $punctuation)) {
                        $data = explode($punctuation, $data)[0];
                        break;
                    }
                }
                return $data;
            }
        } catch (\Exception $e) {
            return $e;
        }
    }


    //发送弹幕通用模块
    private static function sendMsg($info)
    {
        $user_info = User::parseCookies();
        $raw = Curl::get('https://api.live.bilibili.com/room/v1/Room/room_init?id=' . $info['roomid']);
        $de_raw = json_decode($raw, true);

        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $info['content'],
            'rnd' => 0,
            'roomid' => $de_raw['data']['room_id'],
            'csrf' => $user_info['token'],
            'csrf_token' => $user_info['token'],
        ];

        return Curl::post('https://api.live.bilibili.com/msg/send', Sign::api($payload));
    }

    //使用发送弹幕模块
    private static function privateSendMsg($info)
    {
        //TODO 暂时性功能 有需求就修改
        $raw = self::sendMsg($info);
        $de_raw = json_decode($raw, true);

        if ($de_raw['code'] == 1001) {
            Log::warning($de_raw['msg']);
            return false;
        }

        if (!$de_raw['code']) {
            Log::info('弹幕发送成功!');
            return true;
        }

        Log::error("弹幕发送失败, {$de_raw['msg']}");
        return false;
    }
}