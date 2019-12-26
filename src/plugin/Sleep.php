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


class Sleep
{
    use TimeLock;
    // TODO 黑白名单 考虑添加到每个插件内部自动添加
    private static $fillable = ['Login', 'Sleep', 'Daily', 'MasterSite', 'GiftSend', 'Task', 'Silver2Coin', 'GroupSignIn', 'GiftHeart', 'AwardRecord', 'Statistics'];
    private static $guarded = ['Barrage', 'Heart', 'Silver', 'MaterialObject', 'AloneTcpClient', 'ZoneTcpClient', 'StormRaffle', 'GuardRaffle', 'PkRaffle', 'GiftRaffle', 'AnchorRaffle'];
    private static $sleep_section = [];


    public static function run()
    {
        if (getenv('USE_SLEEP') == 'false' || self::getLock() > time()) {
            return;
        }
        self::isPause();
        self::isRefuse();
        self::setLock(5 * 60);
    }

    private static function isPause()
    {
        self::$sleep_section = empty(self::$filter_type) ? explode(',', getenv('SLEEP_SECTION')) : self::$sleep_section;
        if (in_array(date('H'), self::$sleep_section)) {
            $unlock_time = 60 * 60;
            self::stopProc($unlock_time);
            Log::warning('进入自定义休眠时间范围，暂停非必要任务，自动开启！');
        }
        return;
    }

    private static function isRefuse()
    {
        $payload = [];
        $raw = Curl::get('https://api.live.bilibili.com/mobile/freeSilverAward', Sign::api($payload));
        $de_raw = json_decode($raw, true);
        if ($de_raw['msg'] == '访问被拒绝') {
            $unlock_time = strtotime(date("Y-m-d", strtotime("+1 day", time()))) - time();
            self::stopProc($unlock_time);
            Log::warning('账号拒绝访问，暂停非必要任务，自动开启！');
            // 推送被ban信息
            Notice::push('banned', floor($unlock_time / 60 / 60));
        }
        return;
    }

    /**
     * @use 停止运行
     * @param int $unlock_time
     */
    private static function stopProc(int $unlock_time)
    {
        $unlock_time = $unlock_time + 1 * 60;
        // 检测时间提前5分钟
        self::setLock($unlock_time - 5 * 60);
        foreach (self::$fillable as $classname) {
            Log::info("插件 {$classname} 白名单，保持当前状态继续");
        }
        foreach (self::$guarded as $classname) {
            Log::info("插件 {$classname} 黑名单，锁定状态将于" . date("Y-m-d H:i", time() + $unlock_time) . "解除");
            call_user_func(array(__NAMESPACE__ . '\\' . $classname, 'setLock'), $unlock_time);
        }
    }
}