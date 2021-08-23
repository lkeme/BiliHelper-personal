<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

class Sign
{
    /**
     * @use 登录
     * @param $payload
     * @return array
     */
    public static function login($payload): array
    {
        # Android 新
        $app_key = base64_decode(getDevice('bili_a.app_key_n'));
        $app_secret = base64_decode(getDevice('bili_a.secret_key_n'));

        $default = [
            'access_key' => getAccessToken(),
            'actionKey' => 'appkey',
            'appkey' => $app_key,
            'build' => getDevice('bili_a.build'),
            'channel' => getDevice('bili_a.channel'),
            'device' => getDevice('bili_a.device'),
            'mobi_app' => getDevice('bili_a.mobi_app'),
            'platform' => getDevice('bili_a.platform'),
            'ts' => time(),
        ];
        $payload = array_merge($payload, $default);
        return self::encryption($payload, $app_secret);
    }

    /**
     * @use 通用
     * @param $payload
     * @return array
     */
    public static function common($payload): array
    {
        # Android 旧
        $app_key = base64_decode(getDevice('bili_a.app_key'));
        $app_secret = base64_decode(getDevice('bili_a.secret_key'));

        $default = [
            'access_key' => getAccessToken(),
            'actionKey' => 'appkey',
            'appkey' => $app_key,
            'build' => getDevice('bili_a.build'),
            'device' => getDevice('bili_a.device'),
            'mobi_app' => getDevice('bili_a.mobi_app'),
            'platform' => getDevice('bili_a.platform'),
            'ts' => time(),
        ];
        $payload = array_merge($payload, $default);
        return self::encryption($payload, $app_secret);
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