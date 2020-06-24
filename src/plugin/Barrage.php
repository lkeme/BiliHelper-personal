<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class Barrage
{
    use TimeLock;

    public static function run()
    {
        if (self::getLock() > time() || getenv('USE_DANMU') == 'false') {
            return;
        }
        self::setPauseStatus();
        $room_id = empty(getenv('DANMU_ROOMID')) ? Live::getUserRecommend() : Live::getRealRoomID(getenv('DANMU_ROOMID'));
        $msg = empty(getenv('DANMU_CONTENT')) ? self::getMsgInfo() : getenv('DANMU_CONTENT');

        $info = [
            'roomid' => $room_id,
            'content' => $msg,
        ];

        if (self::privateSendMsg($info)) {
            self::setLock(mt_rand(20, 40) * 60);
            return;
        }

        self::setLock(30);
    }


    /**
     * @use 获取随机弹幕
     * @return \Exception|false|mixed|string|null
     */
    private static function getMsgInfo()
    {
        /**
         *  整理一部分API，收集于网络，侵权麻烦联系我删除.
         *  如果设置项不能用可以选择，只保证代码发布时正常.
         *  格式全部为TEXT，可以自己替换.
         */
        $punctuations = ['，', ',', '。', '!', '.', ';', '——'];
        $apis = [
            'https://api.ly522.com/yan.php?format=text',
            'https://v1.hitokoto.cn/?encode=text',
            'https://api.jysafe.cn/yy/',
            'https://api.ooopn.com/yan/api.php?type=text',
            'https://api.imjad.cn/hitokoto/',
            'https://www.ly522.com/hitokoto/',
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
                $data = Curl::request('get', $url);
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


    /**
     * @use 弹幕通用模块
     * @param $info
     * @return array
     */
    private static function sendMsg($info): array
    {
        $user_info = User::parseCookies();
        $url = 'https://api.live.bilibili.com/msg/send';
        $data = Live::getRoomInfo($info['roomid']);
        $payload = [
            'color' => '16777215',
            'fontsize' => 25,
            'mode' => 1,
            'msg' => $info['content'],
            'rnd' => 0,
            'roomid' => $data['data']['room_id'],
            'csrf' => $user_info['token'],
            'csrf_token' => $user_info['token'],
        ];
        $raw = Curl::post('app', $url, Sign::common($payload));
        return json_decode($raw, true) ?? ['code' => 404, 'msg' => '上层数据为空!'];
    }

    /**
     * @use 发送弹幕模块
     * @param $info
     * @return bool
     */
    private static function privateSendMsg($info): bool
    {
        //TODO 短期功能 有需求就修改
        $response = self::sendMsg($info);
        if (isset($response['code']) && $response['code'] == 0) {
            Log::info('活跃弹幕发送成功!');
            return true;
        } else {
            Log::warning("活跃代码发送失败, CODE -> {$response['code']} MSG -> {$response['msg']} ");
            return false;
        }
    }
}