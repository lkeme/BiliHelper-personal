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


class Path
{
    public static function filename(string $path): string
    {
        return pathinfo($path)['filename'];
    }

    public static function extension(string $path): string
    {
        return pathinfo($path)['extension'];
    }

    public static function basename(string $path): string
    {
        return pathinfo($path)['basename'];
    }

    public static function dirname(string $path): string
    {
        return pathinfo($path)['dirname'];
    }

}
