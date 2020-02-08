<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;
use BiliHelper\Core\Config;


class Login
{
    use TimeLock;

    public static function run()
    {
        if (self::getLock()) {
            self::check();
            return;
        }
        Log::info('开始启动程序...');
        if (getenv('ACCESS_TOKEN') == "") {
            Log::info('令牌载入中...');
            self::login();
        }

        Log::info('正在检查令牌合法性...');
        if (!self::info()) {
            Log::warning('令牌即将过期');
            Log::info('申请更换令牌中...');
            if (!self::refresh()) {
                Log::warning('无效令牌，正在重新申请...');
                self::login();
            }
        }
        self::setLock(3600);
    }

    /**
     * @use 检查令牌
     * @return bool
     */
    public static function check()
    {
        if (self::getLock() > time()) {
            return true;
        }
        self::setLock(7200);
        if (!self::info()) {
            Log::warning('令牌即将过期');
            Log::info('申请更换令牌中...');
            if (!self::refresh()) {
                Log::warning('无效令牌，正在重新申请...');
                self::login();
            }
            return false;
        }
        return true;
    }

    /**
     * @use 获取令牌信息
     * @return bool
     */
    protected static function info()
    {
        $access_token = getenv('ACCESS_TOKEN');
        $payload = [
            'access_token' => $access_token,
        ];
        $data = Curl::get('https://passport.bilibili.com/api/v2/oauth2/info', Sign::api($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::error('检查令牌失败', ['msg' => $data['message']]);
            return false;
        }
        Log::info('令牌有效期: ' . date('Y-m-d H:i:s', $data['ts'] + $data['data']['expires_in']));
        return $data['data']['expires_in'] > 14400;
    }

    /**
     * @use 刷新token
     * @return bool
     */
    public static function refresh()
    {
        $access_token = getenv('ACCESS_TOKEN');
        $refresh_token = getenv('REFRESH_TOKEN');
        $payload = [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
        ];
        $data = Curl::post('https://passport.bilibili.com/api/oauth2/refreshToken', Sign::api($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::error('重新生成令牌失败', ['msg' => $data['message']]);
            return false;
        }
        Log::info('令牌生成完毕!');
        $access_token = $data['data']['access_token'];
        Config::put('ACCESS_TOKEN', $access_token);
        Log::info(' > access token: ' . $access_token);
        $refresh_token = $data['data']['refresh_token'];
        Config::put('REFRESH_TOKEN', $refresh_token);
        Log::info(' > refresh token: ' . $refresh_token);
        return true;
    }

    /**
     * @use 普通登陆
     * @param string $captcha
     * @param array $headers
     */
    protected static function login($captcha = '', $headers = [])
    {
        $user = getenv('APP_USER');
        $pass = getenv('APP_PASS');
        if (empty($user) || empty($pass)) {
            Log::error('空白的帐号和口令!');
            die();
        }

        // get PublicKey
        Log::info('正在载入安全模块...');
        $payload = [];
        $data = Curl::post('https://passport.bilibili.com/api/oauth2/getKey', Sign::api($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::error('公钥获取失败', ['msg' => $data['message']]);
            die();
        } else {
            Log::info('安全模块载入完毕！');
        }
        $public_key = $data['data']['key'];
        $hash = $data['data']['hash'];
        openssl_public_encrypt($hash . $pass, $crypt, $public_key);
        for ($i = 0; $i < 30; $i++) {
            // login
            Log::info('正在获取令牌...');
            $payload = [
                'subid' => 1,
                'permission' => 'ALL',
                'username' => $user,
                'password' => base64_encode($crypt),
                'captcha' => $captcha,
            ];
            $data = Curl::post('https://passport.bilibili.com/api/v2/oauth2/login', Sign::api($payload), $headers);
            $data = json_decode($data, true);
            if (isset($data['code']) && $data['code'] == -105) {
                $captcha_data = self::loginWithCaptcha();
                $captcha = $captcha_data['captcha'];
                $headers = $captcha_data['headers'];
                continue;
            }
            // https://passport.bilibili.com/mobile/verifytel_h5.html
            if (!$data['code'] && $data['data']['status']) {
                Log::error('登录失败', ['msg' => '登录异常, 账号启用了设备锁或异地登录需验证手机!']);
                die();
            }
            break;
        }
        if (isset($data['code']) && $data['code']) {
            Log::error('登录失败', ['msg' => $data['message']]);
            die();
        }
        self::saveCookie($data);
        Log::info('令牌获取成功!');
        $access_token = $data['data']['token_info']['access_token'];
        Config::put('ACCESS_TOKEN', $access_token);
        Log::info(' > access token: ' . $access_token);
        $refresh_token = $data['data']['token_info']['refresh_token'];
        Config::put('REFRESH_TOKEN', $refresh_token);
        Log::info(' > refresh token: ' . $refresh_token);

        return;
    }


    /**
     * @use 验证码登陆
     * @return array
     */
    protected static function loginWithCaptcha()
    {
        Log::info('登录需要验证, 启动验证码登录!');
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'User-Agent' => 'bili-universal/8470 CFNetwork/978.0.7 Darwin/18.5.0',
            'Host' => 'passport.bilibili.com',
            'Cookie' => 'sid=blhelper'
        ];
        $data = Curl::other('https://passport.bilibili.com/captcha', null, $headers);
        $data = base64_encode($data);
        $captcha = self::ocrCaptcha($data);
        return [
            'captcha' => $captcha,
            'headers' => $headers,
        ];
    }


    /**
     * @use 识别验证码
     * @param $captcha_img
     * @return mixed
     */
    private static function ocrCaptcha($captcha_img)
    {
        $payload = [
            'image' => (string)$captcha_img
        ];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $data = Curl::other('http://47.102.120.84:19951/', json_encode($payload), $headers);
        $de_raw = json_decode($data, true);
        Log::info("验证码识别结果 {$de_raw['message']}");

        return $de_raw['message'];
    }

    /**
     * @use 保存cookie
     * @param $data
     */
    private static function saveCookie($data)
    {
        Log::info('COOKIE获取成功!');
        //临时保存cookie
        $temp = '';
        $cookies = $data['data']['cookie_info']['cookies'];
        foreach ($cookies as $cookie) {
            $temp .= $cookie['name'] . '=' . $cookie['value'] . ';';
        }
        Config::put('COOKIE', $temp);
        Log::info(' > auth cookie: ' . $temp);
        return;
    }

}