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
        if (getenv('USE_NOTIFY') == 'false') {
            return;
        }
        if (self::filterResultWords($result)) {
            return;
        }
        $uname = User::userInfo() ? getenv('APP_UNAME') : getenv('APP_USER');
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
        $default_words = self::$store->get("Notice.default");;
        $custom_words = empty(getenv('NOTIFY_FILTER_WORDS')) ? [] : explode(',', getenv('NOTIFY_FILTER_WORDS'));
        $total_words = array_merge($default_words, $custom_words);
        foreach ($total_words as $word) {
            if (strpos($result, $word) !== false) {
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
        switch ($type) {
            case 'update':
                $info = [
                    'title' => '程序更新通知',
                    'content' => "[{$now_time}] 用户: {$uname} 程序更新通知: {$result}"
                ];
                break;
            case 'anchor':
                $info = [
                    'title' => '天选时刻获奖记录',
                    'content' => "[{$now_time}] 用户: {$uname} 在天选时刻中获得: {$result}"
                ];
                break;
            case 'raffle':
                $info = [
                    'title' => '实物奖励获奖纪录',
                    'content' => "[{$now_time}] 用户: {$uname} 在实物奖励中获得: {$result}"
                ];
                break;
            case 'gift':
                $info = [
                    'title' => '活动礼物获奖纪录',
                    'content' => "[{$now_time}] 用户: {$uname} 在活动礼物中获得: {$result}"
                ];
                break;
            case 'storm':
                $info = [
                    'title' => '节奏风暴获奖纪录',
                    'content' => "[{$now_time}] 用户: {$uname} 在节奏风暴中获得: {$result}"
                ];
                break;
            case 'cookieRefresh':
                $info = [
                    'title' => 'Cookie刷新',
                    'content' => "[{$now_time}] 用户: {$uname} 刷新Cookie: {$result}"
                ];
                break;
            case 'todaySign':
                $info = [
                    'title' => '每日签到',
                    'content' => "[{$now_time}] 用户: {$uname} 签到: {$result}"
                ];
                break;
            case 'banned':
                $info = [
                    'title' => '任务小黑屋',
                    'content' => "[{$now_time}] 用户: {$uname} 小黑屋: {$result}"
                ];
                break;
            case 'error':
                $info = [
                    'title' => '程序运行错误',
                    'content' => "[{$now_time}] 用户: {$uname} 错误详情: {$result}"
                ];
                break;
            case 'key_expired':
                $info = [
                    'title' => '监控KEY异常',
                    'content' => "[{$now_time}] 用户: {$uname} 监控KEY到期或者错误，请及时查错或续期后重试哦~"
                ];
                break;
            case 'capsule_lottery':
                $info = [
                    'title' => '直播扭蛋抽奖活动',
                    'content' => "[{$now_time}] 用户: {$uname} 详情: {$result}"
                ];
                break;
            case 'activity_lottery':
                $info = [
                    'title' => '主站九宫格抽奖活动',
                    'content' => "[{$now_time}] 用户: {$uname} 详情: {$result}"
                ];
                break;
            default:
                $info = [
                    'title' => '推送消息异常记录',
                    'content' => "[{$now_time}] 用户: {$uname} 推送消息key错误: {$type}->{$result}"
                ];
                break;
        }

        self::sendLog($info);
        return true;
    }

    /**
     * @use 推送消息
     * @param array $info
     */
    private static function sendLog(array $info)
    {
        if (getenv('NOTIFY_SCTKEY')) {
            self::sctSend($info);
        }
        if (getenv('NOTIFY_SCKEY')) {
            self::scSend($info);
        }
        if (getenv('NOTIFY_TELE_BOTTOKEN') && getenv('NOTIFY_TELE_CHATID')) {
            self::teleSend($info);
        }
        if (getenv('NOTIFY_DINGTALK_TOKEN')) {
            self::dingTalkSend($info);
        }
        if (getenv('NOTIFY_PUSHPLUS_TOKEN')) {
            self::pushPlusSend($info);
        }
        if (getenv('NOTIFY_CQ_URL') && getenv('NOTIFY_CQ_TOKEN') && getenv('NOTIFY_CQ_QQ')) {
            self::goCqhttp($info);
        }
    }


    /**
     * @use DingTalkbot推送
     * @doc https://developers.dingtalk.com/document/app/document-upgrade-notice#/serverapi2/qf2nxq
     * @param array $info
     */
    private static function dingTalkSend(array $info)
    {
        Log::info('使用DingTalk机器人推送消息');
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=' . getenv('NOTIFY_DINGTALK_TOKEN');
        $payload = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $info['title'],
                'content' => $info['content'],
            ]
        ];
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8'
        ];
        $raw = Curl::put('other', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['errcode'] == 0) {
            Log::info("推送消息成功: {$de_raw['errmsg']}");
        } else {
            Log::warning("推送消息失败: {$raw}");
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
        $url = 'https://api.telegram.org/bot' . getenv('NOTIFY_TELE_BOTTOKEN');
        $payload = [
            'method' => 'sendMessage',
            'chat_id' => getenv('NOTIFY_TELE_CHATID'),
            'text' => $info['content']
        ];
        $raw = Curl::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        if (array_key_exists('message_id', $de_raw)) {
            Log::info("推送消息成功: {$de_raw['message_id']}");
        } else {
            Log::info("推送消息失败: {$raw}");
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
        $url = 'https://sc.ftqq.com/' . getenv('NOTIFY_SCKEY') . '.send';
        $payload = [
            'text' => $info['title'],
            'desp' => $info['content'],
        ];
        $raw = Curl::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);

        if ($de_raw['errno'] == 0) {
            Log::info("推送消息成功: {$de_raw['errmsg']}");
        } else {
            Log::warning("推送消息失败: {$raw}");
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
        $url = 'https://sctapi.ftqq.com/' . getenv('NOTIFY_SCTKEY') . '.send';
        $payload = [
            'text' => $info['title'],
            'desp' => $info['content'],
        ];
        $raw = Curl::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        // {'message': '[AUTH]用户不存在或者权限不足', 'code': 40001, 'info': '用户不存在或者权限不足', 'args': [None]}
        // {'code': 0, 'message': '', 'data': {'pushid': 'xxxx', 'readkey': 'xxxxx', 'error': 'SUCCESS', 'errno': 0}}
        if ($de_raw['code'] == 0) {
            Log::info("推送消息成功: {$de_raw['data']['pushid']}");
        } else {
            Log::warning("推送消息失败: {$raw}");
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
        $url = 'http://www.pushplus.plus/send';
        $payload = [
            'token' => getenv('NOTIFY_PUSHPLUS_TOKEN'),
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
            Log::info("推送消息成功: {$de_raw['data']}");
        } else {
            Log::warning("推送消息失败: {$raw}");
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
        $url = getenv('NOTIFY_CQ_URL');
        $payload = [
            'access_token' => getenv('NOTIFY_CQ_TOKEN'),
            'user_id' => getenv('NOTIFY_CQ_QQ'),
            'message' => $info['content']
        ];
        $raw = Curl::get('other', $url, $payload);
        // {"data":{"message_id":123456},"retcode":0,"status":"ok"}
        $de_raw = json_decode($raw, true);
        if ($de_raw['retcode'] == 0) {
            Log::info("推送消息成功: {$de_raw['status']}");
        } else {
            Log::warning("推送消息失败: {$raw}");
        }
    }

}