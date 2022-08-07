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

namespace Bhp\Sign;

use Bhp\Util\DesignPattern\SingleTon;

class Sign extends SingleTon
{
    /**
     * @return void
     */
    public function init(): void
    {

    }

    /**
     * 安卓
     * @param array $payload
     * @return array
     */
    public static function android(array $payload): array
    {
        # Android 新
        $app_key = base64_decode(getDevice('app.bili_a.app_key_n'));
        $app_secret = base64_decode(getDevice('app.bili_a.secret_key_n'));
        //
        $default = [
            'access_key' => getU('access_token'),
            'actionKey' => 'appkey',
            'appkey' => $app_key,
            'build' => getDevice('app.bili_a.build'),
            'channel' => getDevice('app.bili_a.channel'),
            'device' => getDevice('app.bili_a.device'),
            'mobi_app' => getDevice('app.bili_a.mobi_app'),
            'platform' => getDevice('app.bili_a.platform'),
            'ts' => time(),
        ];
        //
        $payload = array_merge($payload, $default);
        return self::getInstance()->encryption($payload, $app_secret);
    }


    /**
     * 登录签名
     * @param array $payload
     * @return array
     */
    public static function login(array $payload): array
    {
        return match ((int)getConf('login_mode.mode')) {
            2, 1 => self::android($payload),
            3 => self::tv($payload),
            default => self::common($payload),
        };
    }

    /**
     * 登录签名
     * @param array $payload
     * @return array
     */
    public static function tv(array $payload): array
    {
        # Tv
        $app_key = base64_decode(getDevice('app.bili_t.app_key'));
        $app_secret = base64_decode(getDevice('app.bili_t.secret_key'));
        //
        $default = [
            'appkey' => $app_key,
            'local_id' => 0,
            'ts' => time(),
        ];
        //
        $payload = array_merge($payload, $default);
        return self::getInstance()->encryption($payload, $app_secret);

    }

    /**
     * 通用签名
     * @param array $payload
     * @return array
     */
    public static function common(array $payload): array
    {
        # Android 旧
        $app_key = base64_decode(getDevice('app.bili_a.app_key'));
        $app_secret = base64_decode(getDevice('app.bili_a.secret_key'));

        $default = [
            'access_key' => getU('access_token'),
            'actionKey' => 'appkey',
            'appkey' => $app_key,
            'build' => getDevice('app.bili_a.build'),
            'device' => getDevice('app.bili_a.device'),
            'mobi_app' => getDevice('app.bili_a.mobi_app'),
            'platform' => getDevice('app.bili_a.platform'),
            'ts' => time(),
        ];
        $payload = array_merge($payload, $default);
        return self::getInstance()->encryption($payload, $app_secret);
    }

    /**
     * 加密
     * @param array $payload
     * @param string $app_secret
     * @return array
     */
    protected function encryption(array $payload, string $app_secret): array
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