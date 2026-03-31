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

namespace Bhp\Sign;

use Bhp\Config\Config;
use Bhp\Device\Device;
use Bhp\Runtime\Runtime;
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
        $context = Runtime::getInstance()->context();
        # Android 新
        $app_key = base64_decode((string)Device::getInstance()->get('app.bili_a.app_key_n'));
        $app_secret = base64_decode((string)Device::getInstance()->get('app.bili_a.secret_key_n'));
        //
        $default = [
            'access_key' => $context->auth('access_token'),
            'actionKey' => 'appkey',
            'appkey' => $app_key,
            'build' => Device::getInstance()->get('app.bili_a.build'),
            'channel' => Device::getInstance()->get('app.bili_a.channel'),
            'device' => Device::getInstance()->get('app.bili_a.device'),
            'mobi_app' => Device::getInstance()->get('app.bili_a.mobi_app'),
            'platform' => Device::getInstance()->get('app.bili_a.platform'),
            'c_locale' => 'zh_CN', //  zh-Hans_CH
            's_locale' => 'zh_CN', // zh-Hans_CH
            'disable_rcmd' => '0', //
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
        return match ((int)Config::getInstance()->get('login_mode.mode')) {
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
        $app_key = base64_decode((string)Device::getInstance()->get('app.bili_t.app_key'));
        $app_secret = base64_decode((string)Device::getInstance()->get('app.bili_t.secret_key'));
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
        $context = Runtime::getInstance()->context();
        # Android 旧
        $app_key = base64_decode((string)Device::getInstance()->get('app.bili_a.app_key'));
        $app_secret = base64_decode((string)Device::getInstance()->get('app.bili_a.secret_key'));

        $default = [
            'access_key' => $context->auth('access_token'),
            'actionKey' => 'appkey',
            'appkey' => $app_key,
            'build' => Device::getInstance()->get('app.bili_a.build'),
            'device' => Device::getInstance()->get('app.bili_a.device'),
            'mobi_app' => Device::getInstance()->get('app.bili_a.mobi_app'),
            'platform' => Device::getInstance()->get('app.bili_a.platform'),
            'c_locale' => 'zh_CN', //  zh-Hans_CH
            's_locale' => 'zh_CN', // zh-Hans_CH
            'disable_rcmd' => '0', //
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
