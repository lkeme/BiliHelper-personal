<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Util\ArrayR;

class ArrayR
{
    /**
     * @use 关联数组转字符串
     * @param array $arr
     * @param string $sep1
     * @param string $sep2
     * @return string
     */
    public static function toStr(array $arr, string $sep1 = ':', string $sep2 = '\r\n'): string
    {
        $tmp = '';
        foreach ($arr as $key => $value) {
            $tmp .= "$key$sep1$value$sep2";
        }
        return $tmp;

    }


}
 
 