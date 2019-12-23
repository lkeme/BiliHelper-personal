<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace lkeme\BiliHelper;

class Sign
{
    public static function api($payload)
    {
        # iOS 6680
        $appkey = '27eb53fc9058f8c3';
        $appsecret = 'c2ed53a74eeefe3cf99fbd01d8c9c375';
        # Android
        // $appkey = '1d8b6e7d45233436';
        // $appsecret = '560c52ccd288fed045859ed18bffd973';
        # 云视听 TV
        // $appkey = '4409e2ce8ffd12b8';
        // $appsecret = '59b43e04ad6965f34319062b478f83dd';

        $default = [
            'access_key' => getenv('ACCESS_TOKEN'),
            'actionKey' => 'appkey',
            'appkey' => $appkey,
            'build' => '8230',
            'device' => 'phone',
            'mobi_app' => 'iphone',
            'platform' => 'ios',
            'ts' => time(),
        ];

        $payload = array_merge($payload, $default);
        if (isset($payload['sign'])) {
            unset($payload['sign']);
        }
        ksort($payload);
        $data = http_build_query($payload);
        $payload['sign'] = md5($data . $appsecret);
        return $payload;
    }
}