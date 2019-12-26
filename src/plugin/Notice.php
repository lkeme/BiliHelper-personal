<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class Notice
{
    protected static $type = '';
    protected static $result = '';
    protected static $uname = '';
    protected static $sckey = '';


    /**
     * @use 推送消息
     * @param string $type
     * @param string $result
     */
    public static function push(string $type, string $result = '')
    {
        if (getenv('USE_SCKEY') == "") {
            return;
        }

        self::$type = $type;
        self::$result = $result;
        self::$sckey = getenv('USE_SCKEY');
        self::$uname = User::userInfo() ? getenv('APP_UNAME') : getenv('APP_USER');

        self::sendInfoHandle();
    }


    /**
     * @use 处理信息
     * @return bool
     */
    private static function sendInfoHandle(): bool
    {
        $now_time = date('Y-m-d H:i:s');
        switch (self::$type) {
            case 'anchor':
                $info = [
                    'title' => '天选时刻获奖记录',
                    'content' => '[' . $now_time . ']' . ' 用户: ' . self::$uname . ' 在天选时刻中获得: ' . self::$result,
                ];
                break;
            case 'raffle':
                $info = [
                    'title' => '实物奖励获奖纪录',
                    'content' => '[' . $now_time . ']' . ' 用户: ' . self::$uname . ' 在实物奖励中获得: ' . self::$result,
                ];
                break;
            case 'gift':
                $info = [
                    'title' => '活动礼物获奖纪录',
                    'content' => '[' . $now_time . ']' . ' 用户: ' . self::$uname . ' 在活动礼物中获得: ' . self::$result,
                ];
                break;
            case 'storm':
                $info = [
                    'title' => '节奏风暴获奖纪录',
                    'content' => '[' . $now_time . ']' . ' 用户: ' . self::$uname . ' 在节奏风暴中获得: ' . self::$result,
                ];
                break;
            case 'cookieRefresh':
                $info = [
                    'title' => 'Cookie刷新',
                    'content' => '[' . $now_time . ']' . ' 用户: ' . self::$uname . ' 刷新Cookie: ' . self::$result,
                ];
                break;
            case 'todaySign':
                $info = [
                    'title' => '每日签到',
                    'content' => '[' . $now_time . ']' . ' 用户: ' . self::$uname . ' 签到: ' . self::$result,
                ];
                break;
            case 'banned':
                $info = [
                    'title' => '账号封禁',
                    'content' => '[' . $now_time . ']' . ' 用户: ' . self::$uname . ' 账号被封禁: 程序开始睡眠，凌晨自动唤醒，距离唤醒还有' . self::$result . '小时',
                ];
                break;
            default:
                $info = [
                    'title' => '推送消息异常记录',
                    'content' => '[' . $now_time . ']' . ' 用户: ' . self::$uname . ' 推送消息key错误' . self::$type . self::$result,
                ];
                break;
        }
        self::scSend($info);

        return true;
    }


    /**
     * @use ServerChan发送信息
     * @param array $info
     */
    private static function scSend(array $info)
    {
        $url = "https://sc.ftqq.com/" . self::$sckey . ".send?text=" . urlencode($info['title']) . "&desp=" . urlencode($info['content']);
        $data = Curl::singleRequest('get', $url);
        if (is_null($data)) {
            Log::warning('Server酱推送信息失败,请检查!');
        };
    }
}