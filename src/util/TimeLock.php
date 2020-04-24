<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Util;

use Amp\Delayed;
use BiliHelper\Plugin\Schedule;

trait TimeLock
{
    public static $lock = 0;
    public static $pause_status = false;

    /**
     * @use 设置时间
     * @param int $lock
     */
    public static function setLock(int $lock)
    {
        if (!static::getpauseStatus()) {
            static::$lock = time() + $lock;
        }
    }

    /**
     * @use 获取时间
     * @return int
     */
    public static function getLock(): int
    {
        return static::$lock;
    }

    /**
     * @use used in Amp loop Delayed
     * @return delayed
     */
    public static function Delayed()
    {
        return new Delayed(1000);
    }

    /**
     * @use 定时
     * @param int $hour
     * @return int
     */
    public static function timing(int $hour): int
    {
        // now today tomorrow yesterday
        return strtotime('tomorrow') + ($hour * 60 * 60) - time();
    }

    /**
     * @use 暂停
     */
    public static function pauseLock()
    {
        // 备份几种获取方式 get_called_class()
        // basename(str_replace('\\', '/', $class));
        // substr(strrchr($class, "\\"), 1);
        // substr($class, strrpos($class, '\\') + 1);
        // array_pop(explode('\\', $class));
        Schedule::triggerRefused((new \ReflectionClass(static::class))->getShortName());
    }

    /**
     * @use 取消暂停
     */
    public static function cancelPause()
    {
        static::$lock = false;
    }

    /**
     * @use 暂停状态
     * @return bool
     */
    public static function getPauseStatus()
    {
        return static::$pause_status;
    }

    /**
     * @use 设置状态
     * @param bool $status
     */
    public static function setPauseStatus(bool $status = false)
    {
        self::$pause_status = $status;
    }

}
