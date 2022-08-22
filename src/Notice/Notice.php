<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Notice;

use Bhp\FilterWords\FilterWords;
use Bhp\Log\Log;
use Bhp\Request\Request;
use Bhp\Util\DesignPattern\SingleTon;

class Notice extends SingleTon
{
    /**
     * @var array|string[]
     */
    protected array $headers = [
        'Content-Type' => 'application/json'
    ];


    /**
     * @return void
     */
    public function init(): void
    {
    }


    /**
     * 推送消息
     * @param string $type
     * @param string $msg
     * @return void
     */
    public static function push(string $type, string $msg = ''): void
    {
        // 开关
        if (!getEnable('notify')) return;
        // 过滤词
        if (self::getInstance()->filterMsgWords($msg)) return;
        //
        self::getInstance()->sendInfo($type, $msg);
    }


    /**
     * 发送消息
     * @param string $type
     * @param string $msg
     * @return void
     */
    protected function sendInfo(string $type, string $msg): void
    {
        $info = $this->fillContent($type, $msg);
        //
        if (getConf('notify_sct.sctkey')) {
            $this->sctSend($info);
        }
        if (getConf('notify_sc.sckey')) {
            $this->scSend($info);
        }
        if (getConf('notify_telegram.bottoken') && getConf('notify_telegram.chatid')) {
            $this->teleSend($info);
        }
        if (getConf('notify_dingtalk.token')) {
            $this->dingTalkSend($info);
        }
        if (getConf('notify_pushplus.token')) {
            $this->pushPlusSend($info);
        }
        if (getConf('notify_gocqhttp.target_qq') && getConf('notify_gocqhttp.token') && getConf('notify_gocqhttp.url')) {
            $this->goCqhttp($info);
        }
        if (getConf('notify_debug.token') && getConf('notify_debug.url')) {
            $this->debug($info);
        }
        if (getConf('notify_we_com.token')) {
            $this->weCom($info);
        }
        if (getConf('notify_we_com_app.corp_id') && getConf('notify_we_com_app.corp_secret') && getConf('notify_we_com_app.agent_id')) {
            $this->weComApp($info);
        }
        if (getConf('notify_feishu.token')) {
            $this->feiShuSend($info);
        }
    }

    /**
     * 过滤信息
     * @param string $msg
     * @return bool
     */
    protected function filterMsgWords(string $msg): bool
    {
        $default_words = FilterWords::getInstance()->get("Notice.default");
        $custom_words = explode(',', getConf('notify.filter_words'));
        $total_words = array_merge($default_words, $custom_words);
        foreach ($total_words as $word) {
            if (empty($word)) continue;
            if (str_contains($msg, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 填充消息
     * @param string $type
     * @param string $msg
     * @return array
     */
    protected function fillContent(string $type, string $msg): array
    {
        $now_time = date('Y-m-d H:i:s');
        $uname = getConf('print.uname') ?? getConf('login_account.username');

        $info = match ($type) {
            'update' => [
                'title' => '版本更新通知',
                'content' => "[$now_time] 用户: $uname 程序更新通知: $msg"
            ],
            'anchor' => [
                'title' => '天选时刻获奖记录',
                'content' => "[$now_time] 用户: $uname 在天选时刻中获得: $msg"
            ],
            'raffle' => [
                'title' => '实物奖励获奖纪录',
                'content' => "[$now_time] 用户: $uname 在实物奖励中获得: $msg"
            ],
            'gift' => [
                'title' => '活动礼物获奖纪录',
                'content' => "[$now_time] 用户: $uname 在活动礼物中获得: $msg"
            ],
            'storm' => [
                'title' => '节奏风暴获奖纪录',
                'content' => "[$now_time] 用户: $uname 在节奏风暴中获得: $msg"
            ],
            'cookieRefresh' => [
                'title' => 'Cookie刷新',
                'content' => "[$now_time] 用户: $uname 刷新Cookie: $msg"
            ],
            'todaySign' => [
                'title' => '每日签到',
                'content' => "[$now_time] 用户: $uname 签到: $msg"
            ],
            'banned' => [
                'title' => '任务小黑屋',
                'content' => "[$now_time] 用户: $uname 小黑屋: $msg"
            ],
            'error' => [
                'title' => '程序运行错误',
                'content' => "[$now_time] 用户: $uname 错误详情: $msg"
            ],
            'key_expired' => [
                'title' => '监控KEY异常',
                'content' => "[$now_time] 用户: $uname 监控KEY到期或者错误，请及时查错或续期后重试哦~"
            ],
            'capsule_lottery' => [
                'title' => '直播扭蛋抽奖活动',
                'content' => "[$now_time] 用户: $uname 详情: $msg"
            ],
            'activity_lottery' => [
                'title' => '主站九宫格抽奖活动',
                'content' => "[$now_time] 用户: $uname 详情: $msg"
            ],
            'jury_leave_office' => [
                'title' => '已卸任風機委員',
                'content' => "[$now_time] 用户: $uname 详情: $msg ，请及时关注風機委員连任状态哦~"
            ],
            'jury_auto_apply' => [
                'title' => '嘗試連任風機委員',
                'content' => "[$now_time] 用户: $uname 详情: $msg ，请及时关注風機委員连任状态哦~"
            ],
            default => [
                'title' => $type,
                'content' => "[$now_time] 用户: $uname 详情: $msg"
            ],
        };
        // 添加前缀
        $info['title'] = "【BHP】" . $info['title'];
        //
        return $info;
    }

    /**
     * DingTalkbot推送
     * @doc https://developers.dingtalk.com/document/robots/custom-robot-access
     * @param array $info
     */
    protected function dingTalkSend(array $info): void
    {
        Log::info('使用DingTalk机器人推送消息');
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=' . getConf('notify_dingtalk.token');
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
        $raw = Request::put('other', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['errcode'] == 0) {
            Log::notice("推送消息成功: {$de_raw['errmsg']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * TeleBot推送
     * @doc https://core.telegram.org/bots/api#sendmessage
     * @param array $info
     */
    protected function teleSend(array $info): void
    {
        Log::info('使用Tele机器人推送消息');
        $base_url = getConf('notify_telegram.url') ?: 'https://api.telegram.org/bot';
        $url = $base_url . getConf('notify_telegram.bottoken') . '/sendMessage';
        $payload = [
            'chat_id' => getConf('notify_telegram.chatid'),
            'text' => $info['content']
        ];
        // {"ok":true,"result":{"message_id":7,"from":{"id":,"is_bot":true,"first_name":"","username":""},"chat":{"id":,"first_name":"","username":"","type":"private"},"date":,"text":""}}
        $raw = Request::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        if ($de_raw['ok'] && array_key_exists('message_id', $de_raw['result'])) {
            Log::notice("推送消息成功: MSG_ID->{$de_raw['result']['message_id']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * ServerChan推送
     * https://sc.ftqq.com/
     * @param array $info
     */
    protected function scSend(array $info): void
    {
        Log::info('使用ServerChan推送消息');
        $url = 'https://sc.ftqq.com/' . getConf('notify_sc.sckey') . '.send';
        $payload = [
            'text' => $info['title'],
            'desp' => $info['content'],
        ];
        $raw = Request::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);

        if ($de_raw['errno'] == 0) {
            Log::notice("推送消息成功: {$de_raw['errmsg']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * ServerChan(Turbo)推送
     * @doc https://sct.ftqq.com/
     * @param array $info
     */
    protected function sctSend(array $info): void
    {
        Log::info('使用ServerChan(Turbo)推送消息');
        $url = 'https://sctapi.ftqq.com/' . getConf('notify_sct.sctkey') . '.send';
        $payload = [
            'text' => $info['title'],
            'desp' => $info['content'],
        ];
        $raw = Request::post('other', $url, $payload);
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
     * PushPlus酱推送
     * @doc http://www.pushplus.plus/doc/
     * @param array $info
     */
    protected function pushPlusSend(array $info): void
    {
        Log::info('使用PushPlus酱推送消息');
        $url = 'https://www.pushplus.plus/send';
        $payload = [
            'token' => getConf('notify_pushplus.token'),
            'title' => $info['title'],
            'content' => $info['content']
        ];
        $raw = Request::put('other', $url, $payload, $this->headers);
        // {"code":200,"msg":"请求成功","data":"发送消息成功"}
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 200) {
            Log::notice("推送消息成功: {$de_raw['data']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * GO-CQHTTP推送
     * @doc https://docs.go-cqhttp.org/api/
     * @param array $info
     */
    protected function goCqhttp(array $info): void
    {
        Log::info('使用GoCqhttp推送消息');
        $url = getConf('notify_gocqhttp.url');
        $payload = [
            'access_token' => getConf('notify_gocqhttp.token'),
            'user_id' => getConf('notify_gocqhttp.target_qq'),
            'message' => $info['content']
        ];
        $raw = Request::get('other', $url, $payload);
        // {"data":{"message_id":123456},"retcode":0,"status":"ok"}
        $de_raw = json_decode($raw, true);
        if ($de_raw['retcode'] == 0) {
            Log::notice("推送消息成功: {$de_raw['status']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * 个人调试使用
     * @doc https://localhost:8921/doc
     * @param array $info
     */
    protected function debug(array $info): void
    {
        Log::info('使用Debug推送消息');
        $url = getConf('notify_debug.url');
        $payload = [
            'receiver' => getConf('notify_debug.token'),
            'title' => $info['title'],
            'body' => $info['content'],
            'url' => '',
        ];
        $raw = Request::post('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        // {"success": true, "msg": null, "data": {"errcode": 0, "errmsg": "ok", "msgid": 1231, "token": "456"}}
        if ($de_raw['success']) {
            Log::notice("推送消息成功: {$de_raw['data']['msgid']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * 企业微信群机器人
     * @doc https://open.work.weixin.qq.com/api/doc/90000/90136/91770
     * @param array $info
     */
    protected function weCom(array $info): void
    {
        Log::info('使用weCom推送消息');
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=' . getConf('notify_we_com.token');

        $payload = [
            'msgtype' => 'markdown',
            'markdown' => [
                'content' => "{$info['title']} \n\n{$info['content']}"
            ]
        ];
        $raw = Request::put('other', $url, $payload, $this->headers);
        // {"errcode":0,"errmsg":"ok","created_at":"1380000000"}
        $de_raw = json_decode($raw, true);
        if ($de_raw['errcode'] == 0) {
            Log::notice("推送消息成功: {$de_raw['errmsg']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }

    /**
     * 企业微信应用消息
     * @doc https://open.work.weixin.qq.com/wwopen/devtool/interface?doc_id=10167
     * @param array $info
     */
    protected function weComApp(array $info): void
    {
        Log::info('使用weComApp推送消息');
        $corp_id = getConf('notify_we_com_app.corp_id');
        $corp_secret = getConf('notify_we_com_app.corp_secret');
        $agent_id = getConf('notify_we_com_app.agent_id');
        $to_user = getConf('notify_we_com_app.to_user') ?? '@all';

        // 获取token
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken';
        $payload = [
            'corpid' => $corp_id,
            'corpsecret' => $corp_secret,
        ];
        $raw = Request::get('other', $url, $payload);
        // {"errcode":0,"errmsg":"ok","created_at":"1380000000"}
        $de_raw = json_decode($raw, true);
        if ($de_raw['errcode'] == 0) {
            Log::info("access_token 获取成功: {$de_raw['access_token']}");
        } else {
            Log::warning("access_token 获取失败: $raw");
            return;
        }
        // 推送
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=' . $de_raw['access_token'];
        $payload = [
            'touser' => $to_user,
            'msgtype' => 'text',
            'agentid' => $agent_id,
            'text' => [
                'content' => "{$info['title']} \n\n{$info['content']}"
            ]
        ];
        $raw = Request::put('other', $url, $payload, $this->headers);
        // {"errcode":0,"errmsg":"ok","created_at":"1380000000"}
        $de_raw = json_decode($raw, true);
        if ($de_raw['errcode'] == 0) {
            Log::notice("推送消息成功: {$de_raw['errmsg']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }


    /**
     * 飞书推送
     * @doc https://developers.dingtalk.com/document/robots/custom-robot-access
     * @param array $info
     */
    protected function feiShuSend(array $info): void
    {
        Log::info('使用飞书webhook机器人推送消息');
        $url = 'https://open.feishu.cn/open-apis/bot/v2/hook/' . getConf('notify_feishu.token');
        $payload = [
            'msg_type' => 'text',
            'content' => [
                'text' => $info['title'] . $info['content'],
            ]
        ];
        $headers = [
            'Content-Type' => 'application/json;charset=utf-8'
        ];
        $raw = Request::put('other', $url, $payload, $headers);
        $de_raw = json_decode($raw, true);
        if ($de_raw['StatusCode'] == 0) {
            Log::notice("推送消息成功: {$de_raw['StatusCode']}");
        } else {
            Log::warning("推送消息失败: $raw");
        }
    }


}

