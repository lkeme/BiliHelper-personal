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
use BiliHelper\Util\FilterWords;

class Notice
{
    use FilterWords;

    /**
     * @use 推送消息
     * @param string $type
     * @param string $result
     */
    public static function push(string $type, string $result = '')
    {
        if (!getEnable('notify')) {
            return;
        }
        if (self::filterResultWords($result)) {
            return;
        }
        $uname = getConf('uname', 'print') ?? getConf('username', 'login.account');
        self::sendInfoHandle($type, $uname, $result);
    }

    /**
     * @use 过滤信息
     * @param string $result
     * @return bool
     */
    private static function filterResultWords(string $result): bool
    {
        self::loadJsonData();
        $default_words = self::$store->get("Notice.default");
        $custom_words = explode(',', getConf('filter_words', 'notify'));
        $total_words = array_merge($default_words, $custom_words);
        foreach ($total_words as $word) {
            if (empty($word)) continue;
            if (str_contains($result, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @use 处理信息
     * @param string $type
     * @param string $uname
     * @param string $result
     * @return bool
     */
    private static function sendInfoHandle(string $type, string $uname, string $result): bool
    {
        $now_time = date('Y-m-d H:i:s');
        $info = match ($type) {
            'update' => [
                'title' => '程序更新通知',
                'content' => "[$now_time] 用户: $uname 程序更新通知: $result"
            ],
            'anchor' => [
                'title' => '天选时刻获奖记录',
                'content' => "[$now_time] 用户: $uname 在天选时刻中获得: $result"
            ],
            'raffle' => [
                'title' => '实物奖励获奖纪录',
                'content' => "[$now_time] 用户: $uname 在实物奖励中获得: $result"
            ],
            'gift' => [
                'title' => '活动礼物获奖纪录',
                'content' => "[$now_time] 用户: $uname 在活动礼物中获得: $result"
            ],
            'storm' => [
                'title' => '节奏风暴获奖纪录',
                'content' => "[$now_time] 用户: $uname 在节奏风暴中获得: $result"
            ],
            'cookieRefresh' => [
                'title' => 'Cookie刷新',
                'content' => "[$now_time] 用户: $uname 刷新Cookie: $result"
            ],
            'todaySign' => [
                'title' => '每日签到',
                'content' => "[$now_time] 用户: $uname 签到: $result"
            ],
            'banned' => [
                'title' => '任务小黑屋',
                'content' => "[$now_time] 用户: $uname 小黑屋: $result"
            ],
            'error' => [
                'title' => '程序运行错误',
                'content' => "[$now_time] 用户: $uname 错误详情: $result"
            ],
            'key_expired' => [
                'title' => '监控KEY异常',
                'content' => "[$now_time] 用户: $uname 监控KEY到期或者错误，请及时查错或续期后重试哦~"
            ],
            'capsule_lottery' => [
                'title' => '直播扭蛋抽奖活动',
                'content' => "[$now_time] 用户: $uname 详情: $result"
            ],
            'activity_lottery' => [
                'title' => '主站九宫格抽奖活动',
                'content' => "[$now_time] 用户: $uname 详情: $result"
            ],
            default => [
                'title' => '推送消息异常记录',
                'content' => "[$now_time] 用户: $uname 推送消息key错误: $type->$result"
            ],
        };

        self::sendLog($info);
        return true;
    }

    /**
     * @use 推送消息
     * @param array $info
     */
    private static function sendLog(array $info)
    {
        if (getConf('sctkey', 'notify.sct')) {
            self::sctSend($info);
        }
        if (getConf('sckey', 'notify.sc')) {
            self::scSend($info);
        }
        if (getConf('bottoken', 'notify.telegram') && getConf('chatid', 'notify.telegram')) {
            self::teleSend($info);
        }
        if (getConf('token', 'notify.dingtalk')) {
            self::dingTalkSend($info);
        }
        if (getConf('token', 'notify.pushplus')) {
            self::pushPlusSend($info);
        }
        if (getConf('target_qq', 'notify.gocqhttp') && getConf('token', 'notify.gocqhttp') && getConf('url', 'notify.gocqhttp')) {
            self::goCqhttp($info);
        }
        if (getConf('token', 'notify.debug') && getConf('url', 'notify.debug')) {
            self::debug($info);
        }
    }

    /**
     * @use DingTalkbot推送
     * @doc https://developers.dingtalk.com/document/robots/custom-robot-access
     * @param array $info
     */
    private static function dingTalkSend(array $info)
    {
        Log::info('使用DingTalk机器人推送消息');
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=' . getConf('token', 'notify.dingtalk');
        $payload = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $info['title'],
                'text' => $info['content'],
            ]
        ];
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8'
        ];
        $raw = Curl::put('other', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['errcode'] == 0) {
            Log::notice("推送消息成功: {$de_raw['errmsg']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * @use TeleBot推送
     * @doc https://core.telegram.org/bots/api#sendmessage
     * @param array $info
     */
    private static function teleSend(array $info)
    {
        Log::info('使用Tele机器人推送消息');
        $url = 'https://api.telegram.org/bot' . getConf('bottoken', 'notify.telegram') . '/sendMessage';
        $payload = [
            'chat_id' => getConf('chatid', 'notify.telegram'),
            'text' => $info['content']
        ];
        // {"ok":true,"result":{"message_id":7,"from":{"id":,"is_bot":true,"first_name":"","username":""},"chat":{"id":,"first_name":"","username":"","type":"private"},"date":,"text":""}}
        $raw = Curl::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        if ($de_raw['ok'] && array_key_exists('message_id', $de_raw['result'])) {
            Log::notice("推送消息成功: MSG_ID->{$de_raw['result']['message_id']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * @use ServerChan推送
     * @use https://sc.ftqq.com/
     * @param array $info
     */
    private static function scSend(array $info)
    {
        Log::info('使用ServerChan推送消息');
        $url = 'https://sc.ftqq.com/' . getConf('sckey', 'notify.sc') . '.send';
        $payload = [
            'text' => $info['title'],
            'desp' => $info['content'],
        ];
        $raw = Curl::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);

        if ($de_raw['errno'] == 0) {
            Log::notice("推送消息成功: {$de_raw['errmsg']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * @use ServerChan(Turbo)推送
     * @doc https://sct.ftqq.com/
     * @param array $info
     */
    private static function sctSend(array $info)
    {
        Log::info('使用ServerChan(Turbo)推送消息');
        $url = 'https://sctapi.ftqq.com/' . getConf('sctkey', 'notify.sct') . '.send';
        $payload = [
            'text' => $info['title'],
            'desp' => $info['content'],
        ];
        $raw = Curl::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        // {'message': '[AUTH]用户不存在或者权限不足', 'code': 40001, 'info': '用户不存在或者权限不足', 'args': [None]}
        // {'code': 0, 'message': '', 'data': {'pushid': 'xxxx', 'readkey': 'xxxxx', 'error': 'SUCCESS', 'errno': 0}}
        if ($de_raw['code'] == 0) {
            Log::notice("推送消息成功: {$de_raw['data']['pushid']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * @use PushPlus酱推送
     * @doc http://www.pushplus.plus/doc/
     * @param array $info
     */
    private static function pushPlusSend(array $info)
    {
        Log::info('使用PushPlus酱推送消息');
        $url = 'https://www.pushplus.plus/send';
        $payload = [
            'token' => getConf('token', 'notify.pushplus'),
            'title' => $info['title'],
            'content' => $info['content']
        ];
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $raw = Curl::put('other', $url, $payload, $headers);
        // {"code":200,"msg":"请求成功","data":"发送消息成功"}
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 200) {
            Log::notice("推送消息成功: {$de_raw['data']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * @use GO-CQHTTP推送
     * @doc https://docs.go-cqhttp.org/api/
     * @param array $info
     */
    private static function goCqhttp(array $info)
    {
        Log::info('使用GoCqhttp推送消息');
        $url = getConf('url', 'notify.gocqhttp');
        $payload = [
            'access_token' => getConf('token', 'notify.gocqhttp'),
            'user_id' => getConf('target_qq', 'notify.gocqhttp'),
            'message' => $info['content']
        ];
        $raw = Curl::get('other', $url, $payload);
        // {"data":{"message_id":123456},"retcode":0,"status":"ok"}
        $de_raw = json_decode($raw, true);
        if ($de_raw['retcode'] == 0) {
            Log::notice("推送消息成功: {$de_raw['status']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * @use 个人调试使用
     * @doc
     * @param array $info
     */
    private static function debug(array $info)
    {
        Log::info('使用Debug推送消息');
        $url = getConf('url', 'notify.debug');
        $payload = [
            'receiver' => getConf('token', 'notify.debug'),
            'title' => $info['title'],
            'body' => $info['content'],
            'url' => '',
        ];
        $raw = Curl::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        // {"success": true, "msg": null, "data": {"errcode": 0, "errmsg": "ok", "msgid": 1231, "token": "456"}}
        if ($de_raw['success'] == true) {
            Log::notice("推送消息成功: {$de_raw['data']['msgid']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }

    }

}