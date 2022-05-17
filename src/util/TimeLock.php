<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Util;

use Amp\Delayed;
use BiliHelper\Core\Task;
use BiliHelper\Plugin\Schedule;
use ReflectionClass;

trait TimeLock
{
    public static int $lock = 0;
    public static bool $pause_status = false;

    /**
     * @use 设置时间
     * @param int $lock
     */
    public static function setLock(int $lock): void
    {
        if (!static::getpauseStatus()) {
            Task::getInstance()->_setLock(static::getBaseClass(), time() + $lock);
        }
    }

    /**
     * @use 获取时间
     * @return int
     */
    public static function getLock(): int
    {
        return Task::getInstance()->_getLock(static::getBaseClass());
    }

    /**
     * @use 获取基础CLASS NAME
     * @return string
     */
    public static function getBaseClass(): string
    {
        return basename(str_replace('\\', '/', __CLASS__));
    }

    /**
     * @use used in Amp loop Delayed
     * @return delayed
     */
    public static function Delayed(): Delayed
    {
        return new Delayed(1000);
    }

    /**
     * @use 定时
     * @param int $hour 时
     * @param int $minute 分
     * @param int $seconds 秒
     * @param bool $random 随机一个小时内
     * @return int
     */
    public static function timing(int $hour, int $minute = 0, int $seconds = 0, bool $random = false): int
    {
        $time = strtotime('today') + ($hour * 60 * 60) + ($minute * 60) + ($seconds);
        if ($time > time()) {
            $timing = strtotime('today') + ($hour * 60 * 60) + ($minute * 60) + ($seconds) - time();
        } else {
            $timing = strtotime('tomorrow') + ($hour * 60 * 60) + ($minute * 60) + ($seconds) - time();
        }
        return $random ? $timing + mt_rand(1, 60) * 60 : $timing;
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
    public static function pauseLock(): void
    {
        // 备份几种获取方式 get_called_class()
        // basename(str_replace('\\', '/', $class));
        // substr(strrchr($class, "\\"), 1);
        // substr($class, strrpos($class, '\\') + 1);
        // array_pop(explode('\\', $class));
        Schedule::triggerRefused((new ReflectionClass(static::class))->getShortName());
    }

    /**
     * @use 取消暂停
     */
    public static function cancelPause(): void
    {
        static::$pause_status = false;
    }

    /**
     * @use 暂停状态
     * @return bool
     */
    public static function getPauseStatus(): bool
    {
        return static::$pause_status;
    }

    /**
     * @use 设置状态
     * @param bool $status
     */
    public static function setPauseStatus(bool $status = false): void
    {
        self::$pause_status = $status;
    }

}
