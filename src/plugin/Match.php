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

class Match
{
    use TimeLock;

    private static $tasks = ['sign', 'share'];
    private static $check_status = false;
    private static $room_infos = [
//        'LPL' => [
//            'type_id' => 25,
//            'room_id' => 7734200,
//            'short_room_id' => 6,
//            'lottery_id' => 46,
//            'status' => true
//        ],
//        'OW' => [
//            'type_id' => 26,
//            'room_id' => 14073662,
//            'short_room_id' => 76,
//            'lottery_id' => 52,
//            'status' => true
//        ],
//        'KPL' => [
//            'type_id' => 27,
//            'room_id' => 21144080,
//            'short_room_id' => 55,
//            'lottery_id' => 55,
//            'status' => true
//        ],
    ];

    public static function run()
    {
        if (self::getLock() > time() || getenv('USE_MATCH') == 'false') {
            return;
        }
        // TODO 赛事访问拒绝
        self::initTask();
        self::filterTask();
        self::workTask();
        self::setLock(3600);
    }


    /**
     * @use 初始化任务
     */
    private static function initTask()
    {
        // 检查有效性
        if (!self::$check_status) {
            foreach (self::$room_infos as $title => $room) {
                $status = self::getCapsuleStatus($room['lottery_id'], $room['short_room_id']);
                if (!$status) {
                    self::$room_infos[$title]['status'] = false;
                }
            }
            self::$check_status = true;
        }
    }


    /**
     * @use 过滤任务
     */
    private static function filterTask()
    {
        // 签到任务
        foreach (self::$room_infos as $title => $room) {
            if (!$room['status']) {
                continue;
            }
            $status = self::getSignTask($room['type_id'], $room['room_id'], $room['short_room_id']);
            if ($status) {
                self::$room_infos[$title]['sign'] = true;
            } else {
                self::$room_infos[$title]['sign'] = false;
            }
        }
        // 分享任务
        foreach (self::$room_infos as $title => $room) {
            if (!$room['status']) {
                continue;
            }
            $status = self::getShareTask($room['type_id'], $room['room_id'], $room['short_room_id']);
            if ($status) {
                self::$room_infos[$title]['share'] = true;
            } else {
                self::$room_infos[$title]['share'] = false;
            }
        }
    }


    /**
     * @use 运行任务
     */
    private static function workTask()
    {
        foreach (self::$room_infos as $title => $room) {
            if (!$room['status']) {
                continue;
            }
            if (in_array('sign', $room) && $room['sign']) {
                self::matchSign($room['type_id'], $room['room_id'], $room['short_room_id']);
            }
            if (in_array('share', $room) && $room['share']) {
                self::matchShare($room['type_id'], $room['room_id'], $room['short_room_id']);
            }
        }
    }


    /**
     * @use 获取签到任务
     * @param int $type_id
     * @param int $room_id
     * @param int $short_room_id
     * @return bool
     */
    private static function getSignTask(int $type_id, int $room_id, int $short_room_id): bool
    {
        $url = 'https://api.live.bilibili.com/xlive/general-interface/v1/lpl-task/GetSignTask';
        $payload = [
            'game_type' => $type_id,
        ];
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/{$short_room_id}"
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // 成功 {"code":0,"message":"0","ttl":1,"data":{"id":601,"status":6,"day":30,"awards":[{"title":"彩色弹幕","cover":"https://i0.hdslb.com/bfs/live/9a571f9d82c2a8cbbe869fd92796e70b19f9c2cc.png"},{"title":"助威券","cover":"https://i0.hdslb.com/bfs/activity-plat/static/20200421/158d9f5e9553556421d8e1f652483400/ticket2.png"},{"title":"定制头衔","cover":"https://i0.hdslb.com/bfs/live/928598bde311f01364885ea14658476402eff99f.png"}]}}
        // 默认 {"code":0,"message":"0","ttl":1,"data":{"id":601,"status":3,"day":30,"awards":[{"title":"彩色弹幕","cover":"https://i0.hdslb.com/bfs/live/9a571f9d82c2a8cbbe869fd92796e70b19f9c2cc.png"},{"title":"助威券","cover":"https://i0.hdslb.com/bfs/activity-plat/static/20200421/158d9f5e9553556421d8e1f652483400/ticket2.png"},{"title":"定制头衔","cover":"https://i0.hdslb.com/bfs/live/928598bde311f01364885ea14658476402eff99f.png"}]}}
        if (isset($de_raw['data']['status']) && $de_raw['code'] == 0 && $de_raw['data']['status'] == 3) {
            return true;
        }
        return false;
    }


    /**
     * @use 获取弹幕任务
     * @param int $type_id
     * @param int $room_id
     * @param int $short_room_id
     * @return bool
     */
    private static function getDanmuTask(int $type_id, int $room_id, int $short_room_id): bool
    {
        $url = 'https://api.live.bilibili.com/xlive/general-interface/v1/lpl-task/GetDanmuTask';
        $payload = [
            'game_type' => $type_id,
            '_' => Live::getMillisecond()
        ];
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/{$short_room_id}"
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // 错误 {"code":1002002,"message":"参数错误","ttl":1,"data":{"id":0,"status":0,"awards":null,"progress":null}}
        // 默认 {"code":0,"message":"0","ttl":1,"data":{"id":620,"status":3,"awards":null,"progress":{"cur":0,"max":3}}}
        // 完成 {"code":0,"message":"0","ttl":1,"data":{"id":620,"status":6,"awards":null,"progress":{"cur":3,"max":3}}}
        if (isset($de_raw['data']['status']) && $de_raw['code'] == 0 && $de_raw['data']['status'] == 3) {
            return true;
        }
        return false;
    }


    /**
     * @use 获取分享任务
     * @param int $type_id
     * @param int $room_id
     * @param int $short_room_id
     * @return bool
     */
    private static function getShareTask(int $type_id, int $room_id, int $short_room_id): bool
    {
        $url = 'https://api.live.bilibili.com/xlive/general-interface/v1/lpl-task/GetShareTask';
        $payload = [
            'game_type' => $type_id,
            'room_id' => $short_room_id,
            '_' => Live::getMillisecond()
        ];
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/{$short_room_id}"
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // 成功 {"code":0,"message":"0","ttl":1,"data":{"id":600,"status":6,"awards":null}}
        // 默认 {"code":0,"message":"0","ttl":1,"data":{"id":600,"status":3,"awards":null}}
        if (isset($de_raw['data']['status']) && $de_raw['code'] == 0 && $de_raw['data']['status'] == 3) {
            return true;
        }
        return false;
    }


    /**
     * @use 获取观看任务
     * @param int $type_id
     * @param int $room_id
     * @param int $short_room_id
     * @return bool
     */
    private static function getWatchTask(int $type_id, int $room_id, int $short_room_id): bool
    {
        $url = 'https://api.live.bilibili.com/xlive/general-interface/v1/lpl-task/GetWatchTask';
        $payload = [
            'game_type' => $type_id,
            'room_id' => $room_id,
            '_' => Live::getMillisecond()
        ];
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/{$short_room_id}"
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // 错误 {"code":1002002,"message":"系统繁忙，请稍后再试","ttl":1,"data":{"id":0,"status":0,"progress":null,"awards":null}}
        // 默认 {"code":0,"message":"0","ttl":1,"data":{"id":606,"status":3,"progress":{"cur":0,"max":1800},"awards":null}}
        if (isset($de_raw['data']['status']) && $de_raw['code'] == 0 && $de_raw['data']['status'] == 3) {
            return true;
        }
        return false;
    }


    /**
     * @use 检查奖池状态
     * @param int $lottery_id
     * @param int $short_room_id
     * @return bool
     */
    private static function getCapsuleStatus(int $lottery_id, int $short_room_id): bool
    {
        $url = 'https://api.live.bilibili.com/xlive/web-ucenter/v1/capsule/get_capsule_info_v3';
        $payload = [
            'id' => $lottery_id,
            'from' => 'web',
            '-' => Live::getMillisecond()
        ];
        $headers = [
            'origin' => 'https://live.bilibili.com',
            'referer' => "https://live.bilibili.com/{$short_room_id}"
        ];
        $raw = Curl::get('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        // data -> status 0||2
        // {"code":0,"message":"0","ttl":1,"data":{"coin":9,"rule":"2020年英雄联盟职业联赛春季赛抽奖奖池","gift_list":[{"name":"辣条","num":1,"web_url":"https://i0.hdslb.com/bfs/live/48605b0fe9eca5aba87f93da0fa0aa361c419835.png","mobile_url":"https://i0.hdslb.com/bfs/live/8e7a4dc8de374faee22fca7f9a3f801a1712a36b.png","usage":{"text":"辣条是一种直播虚拟礼物，可以在直播间送给自己喜爱的主播哦~","url":""},"type":1,"expire":"3天","gift_type":"325a347f91903c0353385e343dd358f0"},{"name":"3天头衔续期卡","num":1,"web_url":"https://i0.hdslb.com/bfs/live/48aecec2d7243b6f8bd17f20ff715db89f9adcec.png","mobile_url":"https://i0.hdslb.com/bfs/live/48aecec2d7243b6f8bd17f20ff715db89f9adcec.png","usage":{"text":"3天头衔续期卡*1","url":""},"type":21,"expire":"1周","gift_type":"4bda2f960342d86a426ebc067d3633ed"},{"name":"LPL2020助威","num":1,"web_url":"https://i0.hdslb.com/bfs/live/d9ee9558fcc438c99deb00ed1f6bd3707bac3452.png","mobile_url":"https://i0.hdslb.com/bfs/live/d9ee9558fcc438c99deb00ed1f6bd3707bac3452.png","usage":{"text":"2020LPL限定头衔","url":""},"type":2,"expire":"1周","gift_type":"b114c47920fce2aca5fca7a27cca5915"},{"name":"随机英雄联盟角色手办","num":1,"web_url":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_url":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","usage":{"text":"随机英雄联盟角色手办*1","url":""},"type":100024,"expire":"当天","gift_type":"6a4ae5853753d67d07cea2b1750795f4"},{"name":"2020LPL彩色弹幕","num":1,"web_url":"https://i0.hdslb.com/bfs/live/9a571f9d82c2a8cbbe869fd92796e70b19f9c2cc.png","mobile_url":"https://i0.hdslb.com/bfs/live/9a571f9d82c2a8cbbe869fd92796e70b19f9c2cc.png","usage":{"text":"LPL专属彩色弹幕","url":""},"type":20,"expire":"3天","gift_type":"14e40c6949800b5d840011e47e54d0c5"},{"name":"7天头衔续期卡","num":1,"web_url":"https://i0.hdslb.com/bfs/live/a2ffb62dc90d4896ddc3d1dcdbe83ac5d1dd7328.png","mobile_url":"https://i0.hdslb.com/bfs/live/a2ffb62dc90d4896ddc3d1dcdbe83ac5d1dd7328.png","usage":{"text":"7天头衔续期卡*1","url":""},"type":21,"expire":"1周","gift_type":"bbfc114b65126486a40c81daedd911e5"},{"name":"2020LPL春季赛助威券","num":1,"web_url":"https://i0.hdslb.com/bfs/live/be4cdecc4809caf8aa21817880a3283672b5a477.png","mobile_url":"https://i0.hdslb.com/bfs/live/be4cdecc4809caf8aa21817880a3283672b5a477.png","usage":{"text":"再来一次！(✪ω✪)","url":""},"type":22,"expire":"当天","gift_type":"96d2b8187ec6564fa40733153a41ac14"},{"name":"30天头衔续期卡","num":1,"web_url":"https://i0.hdslb.com/bfs/live/fc49e08115db6edd0276fba69ed8835a64714441.png","mobile_url":"https://i0.hdslb.com/bfs/live/fc49e08115db6edd0276fba69ed8835a64714441.png","usage":{"text":"30天头衔续期卡*1","url":""},"type":21,"expire":"1周","gift_type":"02810fd04244c47952bd4ed0b35617db"},{"name":"辣条","num":233,"web_url":"https://i0.hdslb.com/bfs/live/48605b0fe9eca5aba87f93da0fa0aa361c419835.png","mobile_url":"https://i0.hdslb.com/bfs/live/8e7a4dc8de374faee22fca7f9a3f801a1712a36b.png","usage":{"text":"辣条是一种直播虚拟礼物，可以在直播间送给自己喜爱的主播哦~","url":""},"type":1,"expire":"3天","gift_type":"a6d260760dfb1fe9f5375b3c8c7bd7ad"},{"name":"随机提伯斯熊毛绒公仔","num":1,"web_url":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_url":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","usage":{"text":"提伯斯熊毛绒公仔*1","url":""},"type":100024,"expire":"当天","gift_type":"2df71ff3306a4a2b4a627889cbd63c5b"}],"change_num":10000,"status":0,"is_login":true,"user_score":90000,"list":[{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-04-23","name":"nXBo7p0svjm","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-04-20","name":"z98rwt","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-04-18","name":"wBQW6Z6jgbb","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-04-13","name":"dU9449p1zkz","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-04-10","name":"ckcs8151","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-04-06","name":"l1d9fgn1gl","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-03-31","name":"rlBF7ivbffe","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-03-29","name":"卟要悔","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1},{"num":1,"gift":"随机提伯斯熊毛绒公仔","date":"2020-03-23","name":"就这样8丶","web_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","mobile_image":"https://i0.hdslb.com/bfs/live/61414ab727c55cd1de8fb5c1c79a5a05dada3a55.png","count":1},{"num":1,"gift":"随机英雄联盟角色手办","date":"2020-03-17","name":"bIud77Vsory","web_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","mobile_image":"https://i0.hdslb.com/bfs/live/4a2f604ef7b3dad583c054d4ffdb30e37f37ad9c.png","count":1}]}}
        if (isset($de_raw['data']['status']) && $de_raw['code'] == 0 && $de_raw['data']['status'] == 0) {
            return true;
        }
        return false;
    }


    /**
     * @use 分享任务
     * @param int $type_id
     * @param int $room_id
     * @param int $short_room_id
     * @return bool
     */
    private static function matchShare(int $type_id, int $room_id, int $short_room_id): bool
    {
        $user_info = User::parseCookies();
        $url = 'https://api.live.bilibili.com/xlive/general-interface/v1/lpl-task/MatchShare';
        $payload = [
            'game_type' => $type_id,
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => ''
        ];
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin' => 'https://live.bilibili.com',
            'Referer' => "https://live.bilibili.com/{$short_room_id}"
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"status":1}}
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            Log::notice("房间 {$short_room_id} 赛事 {$type_id} 分享成功~");
            return true;
        }
        Log::warning("房间 {$short_room_id} 赛事 {$type_id} 分享失败~");
        return false;
    }


    /**
     * @use 签到任务
     * @param int $type_id
     * @param int $room_id
     * @param int $short_room_id
     * @return bool
     */
    private static function matchSign(int $type_id, int $room_id, int $short_room_id): bool
    {
        $user_info = User::parseCookies();
        $url = 'https://api.live.bilibili.com/xlive/general-interface/v1/lpl-task/MatchSign';
        $payload = [
            'room_id' => $room_id,
            'game_type' => $type_id,
            'csrf_token' => $user_info['token'],
            'csrf' => $user_info['token'],
            'visit_id' => ''
        ];
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin' => 'https://live.bilibili.com',
            'Referer' => "https://live.bilibili.com/{$short_room_id}"
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"status":1,"awards":[{"title":"彩色弹幕","cover":"https://i0.hdslb.com/bfs/live/9a571f9d82c2a8cbbe869fd92796e70b19f9c2cc.png","num":1}],"is_focus":1}}
        $raw = Curl::post('pc', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0) {
            Log::notice("房间 {$short_room_id} 赛事 {$type_id} 签到成功~");
            return true;
        }
        Log::warning("房间 {$short_room_id} 赛事 {$type_id} 签到失败~");
        return false;
    }

    private static function matchDanmu()
    {

    }

    private static function matchWatch()
    {

    }


}