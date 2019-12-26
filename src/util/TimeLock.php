<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace BiliHelper\Util;


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

}