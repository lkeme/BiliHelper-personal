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
     * @param int $minute
     * @param int $seconds
     * @return int
     */
    public static function timing(int $hour, int $minute = 0, int $seconds = 0): int
    {
        $time = strtotime('today') + ($hour * 60 * 60) + ($minute * 60) + ($seconds);
        if ($time > time()) {
            return strtotime('today') + ($hour * 60 * 60) + ($minute * 60) + ($seconds) - time();
        } else {
            return strtotime('tomorrow') + ($hour * 60 * 60) + ($minute * 60) + ($seconds) - time();
        }
    }


    /**
     * @use 判断是否在时间内
     * @param string $first_time
     * @param string $second_time
     * @return bool
     */
    public static function inTime(string $first_time, string $second_time): bool
    {
        #判断当前时间是否在时间段内，如果是，则执行
        $Day = date('Y-m-d ', time());
        $timeBegin = strtotime($Day . $first_time);
        $timeEnd = strtotime($Day . $second_time);
        $curr_time = time();
        if ($curr_time >= $timeBegin && $curr_time <= $timeEnd) {
            return true;
        }
        return false;
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
