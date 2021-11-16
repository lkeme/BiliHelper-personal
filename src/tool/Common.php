<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Tool;

class Common
{

    /**
     * @use 获取十三位时间戳
     * @return int
     */
    public static function getUnixTimestamp(): int
    {
        list($t1, $t2) = explode(' ', microtime());
        // return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
        return intval(sprintf('%u', (floatval($t1) + floatval($t2)) * 1000));
    }

    /**
     * @use 获取当月第一天及最后一天
     * $day[0] 第一天
     * $day[1] 最后一天
     * @return array
     */
    public static function getTheMonth(): array
    {
        $today = date("Y-m-d");
        $first_day = date('Y-m-01', strtotime($today));
        $last_day = date('Y-m-d', strtotime("$first_day +1 month -1 day"));
        return array($first_day, $last_day);
    }


    /**
     * @use 替换字符串
     * @param $str
     * @param $start
     * @param int $end
     * @param string $dot
     * @param string $charset
     * @return string
     */
    public static function replaceStar($str, $start, int $end = 0, string $dot = "*", string $charset = "UTF-8"): string
    {
        $len = mb_strlen($str, $charset);
        if ($start == 0 || $start > $len) {
            $start = 1;
        }
        if ($end != 0 && $end > $len) {
            $end = $len - 2;
        }
        $endStart = $len - $end;
        $top = mb_substr($str, 0, $start, $charset);
        $bottom = "";
        if ($endStart > 0) {
            $bottom = mb_substr($str, $endStart, $end, $charset);
        }
        $len = $len - mb_strlen($top, $charset);
        $len = $len - mb_strlen($bottom, $charset);
        $newStr = $top;
        $newStr .= str_repeat($dot, $len);
        $newStr .= $bottom;
        return $newStr;
    }


}


