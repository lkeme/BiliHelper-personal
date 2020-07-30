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


class Generator
{
    /**
     * @use 生成uuid4
     * @return string
     */
    public static function uuid4(): string
    {
        // 标准的UUID格式为：xxxxxxxx-xxxx-xxxx-xxxxxx-xxxxxxxxxx(8-4-4-4-12)
        // var T = "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function(t) {
        //     var e = 16 * Math.random() | 0;
        //     return ("x" === t ? e : 3 & e | 8).toString(16)
        // });
        $chars = md5(uniqid(mt_rand(), true));
        $chars = substr_replace($chars, "4", 12, 1);
        $chars = substr_replace($chars, "a", 16, 1);
        $uuid = substr($chars, 0, 8) . '-'
            . substr($chars, 8, 4) . '-'
            . substr($chars, 12, 4) . '-'
            . substr($chars, 16, 4) . '-'
            . substr($chars, 20, 12);
        return $uuid;
    }

    /**
     * @use 生成hash
     * @return string
     */
    public static function hash(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()+-';
        $random = $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)] . $chars[mt_rand(0, 73)];//Random 5 times
        $content = uniqid() . $random;  // 类似 5443e09c27bf4aB4uT
        return md5($content); // sha1
    }


}
