<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;

class Sign
{

//    /**
//     * @use 登录
//     * @param $payload
//     * @return array
//     */
//    public static function login($payload)
//    {
//        # 云视听 TV
//        $appkey = '4409e2ce8ffd12b8';
//        $appsecret = '59b43e04ad6965f34319062b478f83dd';
//
//        $default = [
//            'access_key' => getenv('ACCESS_TOKEN'),
//            'actionKey' => 'appkey',
//            'appkey' => $appkey,
//            'build' => 101800,
//            'device' => 'android',
//            'mobi_app' => 'android_tv_yst',
//            'platform' => 'android',
//            'ts' => time(),
//        ];
//        $payload = array_merge($payload, $default);
//        return self::encryption($payload, $appsecret);
//    }

    /**
     * @use 登录
     * @param $payload
     * @return array
     */
    public static function login($payload)
    {
        # Android 新
        $appkey = 'bca7e84c2d947ac6';
        $appsecret = '60698ba2f68e01ce44738920a0ffe768';

        $default = [
            'access_key' => getenv('ACCESS_TOKEN'),
            'actionKey' => 'appkey',
            'appkey' => $appkey,
            'build' => 6030600,
            'channel'=>'bili',
            'device' => 'phone',
            'mobi_app' => 'android',
            'platform' => 'android',
            'ts' => time(),
        ];
        $payload = array_merge($payload, $default);
        return self::encryption($payload, $appsecret);
    }

    /**
     * @use 通用
     * @param $payload
     * @return array
     */
    public static function common($payload)
    {
        # iOS 6680
//        $appkey = '27eb53fc9058f8c3';
//        $appsecret = 'c2ed53a74eeefe3cf99fbd01d8c9c375';
        # Android 旧
        $appkey = '1d8b6e7d45233436';
        $appsecret = '560c52ccd288fed045859ed18bffd973';

        $default = [
            'access_key' => getenv('ACCESS_TOKEN'),
            'actionKey' => 'appkey',
            'appkey' => $appkey,
            'build' => 5511400,
            'device' => 'android',
            'mobi_app' => 'android',
            'platform' => 'android',
            'ts' => time(),
        ];
        $payload = array_merge($payload, $default);
        return self::encryption($payload, $appsecret);
    }


    /**
     * @use 加密
     * @param array $payload
     * @param string $app_secret
     * @return array
     */
    private static function encryption(array $payload, string $app_secret): array
    {
        if (isset($payload['sign'])) {
            unset($payload['sign']);
        }
        ksort($payload);
        $data = http_build_query($payload);
        $payload['sign'] = md5($data . $app_secret);
        return $payload;
    }
}