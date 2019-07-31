<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 *  LastAPIChecked: 20190731
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
        /*
         *  整理一部分API，收集于网络，侵权麻烦联系我删除.
         *  如果设置项不能用可以选择，只保证代码发布时正常.
         *  格式全部为TEXT，请自己测试好了再放进配置文件.
         *  -7. https://api.lwl12.com/hitokoto/v1?encode=realjso
         *  -6. https://api.ly522.com/yan.php?format=text
         *  -5. https://v1.hitokoto.cn/?encode=text
         *  -4. https://api.jysafe.cn/yy/
         *  -3. https://m.mom1.cn/api/
         *  -2. https://api.ooopn.com/yan/api.php?type=text
         *  -1. https://api.imjad.cn/hitokoto/
         *  0. https://www.ly522.com/hitokoto/
         *  1. https://www.tddiao.online/word/
         *  2. https://api.guoch.xyz/
         *  3. http://www.ooomg.cn/dutang
         *  4. https://api.gushi.ci/rensheng.txt
         *  5. https://api.itswincer.com/hitokoto/v2/
         *  6. http://api.imiliy.cn/get/
         *  7. http://api.dsecret.com/yiyan/
         *  8. https://api.xygeng.cn/dailywd/api/api.php
         */
        try {
            $url = getenv('CONTENT_FETCH');
            if (empty($url)) {
                exit('活跃弹幕配置错误，请检查相应配置');
            }
            $data = Curl::get($url);
            $punctuations = ['，', ',', '。', '!', '.', ';', '——'];
            foreach ($punctuations as $punctuation) {
                if (strpos($data, $punctuation)) {
                    $data = explode($punctuation, $data)[0];
                    break;
                }
            }
            return $data;

        } catch (\Exception $e) {
            return $e;
        }
    }

    //转换信息
    private static function convertInfo()
    {
        preg_match('/bili_jct=(.{32})/', getenv('COOKIE'), $token);
        $token = isset($token[1]) ? $token[1] : '';
        return $token;
    }

    //发送弹幕通用模块
    private static function sendMsg($info)
    {
        $raw = Curl::get('https://api.live.bilibili.com/room/v1/Room/room_init?id=' . $info['roomid']);
        $de_raw = json_decode($raw, true);

        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $info['content'],
            'rnd' => 0,
            'roomid' => $de_raw['data']['room_id'],
            'csrf' => self::convertInfo(),
            'csrf_token' => self::convertInfo(),
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