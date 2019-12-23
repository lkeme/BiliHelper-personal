<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace lkeme\BiliHelper;

class Notice
{
    protected static $type = '';
    protected static $result = '';
    protected static $uname = '';
    protected static $sckey = '';

    // RUN
    public static function run($type, $result = '')
    {
        if (getenv('USE_SCKEY') == "") {
            return;
        }

        self::$type = $type;
        self::$result = $result;
        self::$sckey = getenv('USE_SCKEY');
        self::$uname = User::userInfo() ? getenv('APP_UNAME') : getenv('APP_USER');

        self::infoSendManager();

    }

    // 信息管理分发
    private static function infoSendManager(): bool
    {
        $nowtime = date('Y-m-d H:i:s');

        switch (self::$type) {
            case 'raffle':
                $info = [
                    'title' => '活动抽奖结果',
                    'content' => '[' . $nowtime . ']' . ' 用户: ' . self::$uname . ' 在活动抽奖中获得: ' . self::$result,
                ];
                break;
            case 'storm':
                $info = [
                    'title' => '节奏风暴中奖结果',
                    'content' => '[' . $nowtime . ']' . ' 用户: ' . self::$uname . ' 在节奏风暴抽奖中: ' . self::$result,
                ];
                break;
            case 'active':
                $info = [
                    'title' => '活动中奖结果',
                    'content' => '[' . $nowtime . ']' . ' 用户: ' . self::$uname . ' 在活动抽奖中获得: ' . self::$result,
                ];
                break;
            case 'cookieRefresh':
                $info = [
                    'title' => 'Cookie刷新',
                    'content' => '[' . $nowtime . ']' . ' 用户: ' . self::$uname . ' 刷新Cookie: ' . self::$result,
                ];
                break;
            case 'loginInit':
                break;

            case 'todaySign':
                $info = [
                    'title' => '每日签到',
                    'content' => '[' . $nowtime . ']' . ' 用户: ' . self::$uname . ' 签到: ' . self::$result,
                ];
                break;
            case 'winIng':
                $info = [
                    'title' => '实物中奖',
                    'content' => '[' . $nowtime . ']' . ' 用户: ' . self::$uname . ' 实物中奖: ' . self::$result,
                ];
                break;
            case 'banned':
                $info = [
                    'title' => '账号封禁',
                    'content' => '[' . $nowtime . ']' . ' 用户: ' . self::$uname . ' 账号被封禁: 程序开始睡眠,凌晨自动唤醒,距离唤醒还有' . self::$result . '小时',
                ];
                break;
            default:
                break;
        }
        self::scSend($info);

        return true;
    }

    // 发送信息
    private static function scSend($info)
    {

        $postdata = http_build_query(
            [
                'text' => $info['title'],
                'desp' => $info['content']
            ]
        );

        $opts = ['http' =>
            [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            ]
        ];
        try {
            $context = stream_context_create($opts);
            file_get_contents('https://sc.ftqq.com/' . self::$sckey . '.send', false, $context);

        } catch (\Exception $e) {
            Log::warning('Server酱推送信息失败,请检查!');
        }
        return;
        //return $result = file_get_contents('https://sc.ftqq.com/' . $this->_sckey . '.send', false, $context);
    }
}