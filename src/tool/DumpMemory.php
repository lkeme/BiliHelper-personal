<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 *  Source: https://github.com/anhao/bv2av/
 */

namespace BiliHelper\Tool;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;

class DumpMemory
{
    public static function dd($title)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        $size = memory_get_usage(true);
        $memory = @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
        Log::warning("{$title} # 内存 # {$memory}");
    }
}
