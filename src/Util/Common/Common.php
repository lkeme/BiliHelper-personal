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

namespace Bhp\Util\Common;

class Common
{

    /**
     * 获取十三位时间戳
     * @return int
     */
    public static function getUnixTimestamp(): int
    {
        list($t1, $t2) = explode(' ', microtime());
        // return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
        return intval(sprintf('%u', (floatval($t1) + floatval($t2)) * 1000));
    }

    /**
     * 获取当月第一天及最后一天
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
     * 替换字符串
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

    /**
     * 检查手机号格式
     * @param string $phone
     * @return bool
     */
    public static function checkPhone(string $phone): bool
    {
        //  /^1[3456789]{1}\d{9}$/
        if (!preg_match("/^1[3456789]\d{9}$/", $phone)) {
            return false;
        }
        return true;
    }

    /**
     * 自定义UUID
     * @param string $data
     * @return string
     */
    public static function customCreateUUID(string $data): string
    {
        //strrev
        $chars = md5($data);
        return substr($chars, 0, 8) . '-'
            . substr($chars, 8, 4) . '-'
            . substr($chars, 12, 4) . '-'
            . substr($chars, 16, 4) . '-'
            . substr($chars, 20, 12);
    }

    /**
     * @param int $length
     * @return string
     */
    public static function randString(int $length):string
    {
//        $output='';
//        for ($a = 0; $a<$length; $a++) {
//            $output .= chr(mt_rand(33, 126));    //生成php随机数
//        }
//        return $output;
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
        for ($i = 0; $i < $length; $i++) {
            $key .= $pattern[mt_rand(0, 35)];    //生成php随机数
        }
        return $key;
    }

}