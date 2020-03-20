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

trait TimeLock
{
    public static $lock = 0;

    /**
     * @use 设置时间
     * @param int $lock
     */
    public static function setLock(int $lock)
    {
        static::$lock = time() + $lock;
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

}
