<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
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
     * 关联数组转字符串
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

    /**
     * 随机部分切片
     * @param array $arr
     * @param int $num
     * @param bool $random
     * @return array
     */
    public static function toSlice(array $arr, int $num, bool $random = true): array
    {
        if ($random) {
            //shuffle 将数组顺序随即打乱
            shuffle($arr);
        }
        //array_slice 取该数组中的某一段
        return array_slice($arr, 0, $num);
    }

    /**
     * 随机获取数组中的一个元素
     * @param array $arr
     * @return string
     */
    public static function toRand(array $arr): string
    {
        return $arr[array_rand($arr)];
    }
}

