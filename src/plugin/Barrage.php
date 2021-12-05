<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;
use Exception;

class Barrage
{
    use TimeLock;

    /**
     * @use run
     */
    public static function run()
    {
        if (self::getLock() > time() || !getEnable('barrage')) {
            return;
        }
        self::setPauseStatus();
        if (self::sendMsg()) {
            self::setLock(mt_rand(240, 300) * 60);
            return;
        }
        self::setLock(15 * 60);
    }

    /**
     * 获取一言api消息
     * @param bool $sep
     * @return string
     */
    private static function getMsgInfo(bool $sep = true): string
    {
        /**
         *  整理一部分API，收集于网络，侵权麻烦联系我删除.
         *  如果设置项不能用可以选择，只保证代码发布时正常.
         *  格式全部为TEXT，可以自己替换.
         */
        $punctuations = $sep ? ['，', ',', '。', '!', '.', ';', '——'] : [];
        $apis = [
            'https://v1.hitokoto.cn/?encode=text',
            'https://api.ly522.com/Api/YiYan?format=text',
            'https://api.jysafe.cn/yy/',
            'https://api.imjad.cn/hitokoto/',
            'https://www.ly522.com/hitokoto/',
            'https://api.guoch.xyz/',
            'https://api.gushi.ci/rensheng.txt',
            'https://api.itswincer.com/hitokoto/v2/',
            // 'http://www.ooomg.cn/dutang/',
            // 'http://api.dsecret.com/yiyan/',
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
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }


    /**
     * @use 活跃弹幕
     * @return bool
     */
    private static function sendMsg(): bool
    {
        $room_id = empty($room_id = getConf('room_id', 'barrage')) ? Live::getUserRecommend() : Live::getRealRoomID($room_id);
        $content = empty($msg = getConf('content', 'barrage')) ? self::getMsgInfo() : $msg;

        $response = Live::sendBarragePC($room_id, $content);
        // {"code":0,"data":[],"message":"","msg":""}
        // {"code":0,"message":"你被禁言啦","msg":"你被禁言啦"}
        // Todo 长度限制
        if (isset($response['code']) && $response['code'] == 0 && isset($response['data'])) {
            Log::notice("在直播间@$room_id 发送活跃弹幕成功 CODE -> {$response['code']}");
            return true;
        } else {
            Log::warning("在直播间@$room_id 发送活跃弹幕失败 CODE -> {$response['code']} MSG -> {$response['message']} ");
            return false;
        }
    }
}