<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 *  Source: https://github.com/anhao/bv2av/
 */

namespace BiliHelper\Tool;

use BiliHelper\Core\Log;

class DumpMemory
{
    public static function dd($title): void
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        $size = memory_get_usage(true);
        $memory = @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
        Log::warning("$title # 内存 # $memory");
    }
}
