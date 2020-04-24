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
use BiliHelper\Core\Config;
use BiliHelper\Util\TimeLock;


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
        $url = 'https://passport.bilibili.com/api/v2/oauth2/info';
        $payload = [
            'access_token' => getenv('ACCESS_TOKEN'),
        ];
        $data = Curl::get('app', $url, Sign::common($payload));
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
        $url = 'https://passport.bilibili.com/api/oauth2/refreshToken';
        $payload = [
            'access_token' => getenv('ACCESS_TOKEN'),
            'refresh_token' => getenv('REFRESH_TOKEN'),
        ];
        $data = Curl::post('app', $url, Sign::common($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::error('重新生成令牌失败', ['msg' => $data['message']]);
            return false;
        }
        Log::info('令牌生成完毕!');
        $access_token = $data['data']['access_token'];
        Config::put('ACCESS_TOKEN', $access_token);
        Log::info(" > access token: {$access_token}");
        $refresh_token = $data['data']['refresh_token'];
        Config::put('REFRESH_TOKEN', $refresh_token);
        Log::info(" > refresh token: {$refresh_token}");
        if (!self::saveCookie()) {
            self::clearAccount();
            die();
        };
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
        self::clearAccount();
        // get PublicKey
        Log::info('正在载入安全模块...');
        $url = 'https://passport.snm0516.aisee.tv/api/oauth2/getKey';
        $payload = [];
        $data = Curl::post('app', $url, Sign::login($payload));
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
            $url = 'https://passport.snm0516.aisee.tv/api/tv/login';
            $payload = [
                'channel' => 'master',
                'token' => '5598158bcd8511e9',
                'guid' => 'XYEBAA3E54D502E73BD606F0589A356902FCF',
                'username' => $user,
                'password' => base64_encode($crypt),
                'captcha' => $captcha,
            ];
            $data = Curl::post('app', $url, Sign::login($payload), $headers);
            $data = json_decode($data, true);
            if (isset($data['code']) && $data['code'] == -105) {
                $captcha_data = self::loginWithCaptcha();
                $captcha = $captcha_data['captcha'];
                $headers = $captcha_data['headers'];
                continue;
            }
            // https://passport.bilibili.com/mobile/verifytel_h5.html
            if ($data['code'] == -2100) {
                Log::error('登录失败', ['msg' => '登录异常, 账号启用了设备锁或异地登录需验证手机!']);
                die();
            }
            break;
        }
        if (isset($data['code']) && $data['code']) {
            Log::error('登录失败', ['msg' => $data['message']]);
            die();
        }
        Log::info('令牌获取成功!');
        $access_token = $data['data']['token_info']['access_token'];
        Config::put('ACCESS_TOKEN', $access_token);
        Log::info(" > access token: {$access_token}");
        $refresh_token = $data['data']['token_info']['refresh_token'];
        Config::put('REFRESH_TOKEN', $refresh_token);
        Log::info(" > refresh token: {$refresh_token}");
        if (!self::saveCookie()) {
            self::clearAccount();
            die();
        };
        return;
    }


    /**
     * @use 验证码登陆
     * @return array
     */
    protected static function loginWithCaptcha()
    {
        Log::info('登录需要验证, 启动验证码登录!');
        $url = 'https://passport.snm0516.aisee.tv/api/captcha';
        $payload = [
            'token' => '5598158bcd8511e9'
        ];
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'Host' => 'passport.snm0516.aisee.tv',
            'Cookie' => 'sid=blhelper'
        ];
        $data = Curl::get('other', $url, $payload, $headers);
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
        $url = 'http://47.102.120.84:19951/';
        $payload = [
            'image' => (string)$captcha_img
        ];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $data = Curl::put('other', $url, $payload, $headers);
        $de_raw = json_decode($data, true);
        Log::info("验证码识别结果 {$de_raw['message']}");
        return $de_raw['message'];
    }

    /**
     * @use token转cookie
     * @return string
     */
    private static function token2cookie(): string
    {
        $url = 'https://passport.bilibili.com/api/login/sso';
        $payload = [
            'gourl' => 'https%3A%2F%2Faccount.bilibili.com%2Faccount%2Fhome'
        ];
        $response = Curl::headers('app', $url, Sign::common($payload));
        $headers = $response['Set-Cookie'];
        $cookies = [];
        foreach ($headers as $header) {
            preg_match_all('/^(.*);/iU', $header, $cookie);
            array_push($cookies, $cookie[0][0]);
        }
        return implode("", array_reverse($cookies));
    }


    /**
     * @use 保存cookie
     * @return bool
     */
    private static function saveCookie(): bool
    {
        $cookies = self::token2cookie();
        if ($cookies == '') {
            Log::error("COOKIE获取失败 {$cookies}");
            return false;
        }
        Log::info("COOKIE获取成功 !");
        Log::info(" > cookie: {$cookies}");
        Config::put('COOKIE', $cookies);
        return true;
    }

    /**
     * @use 清除已有
     */
    private static function clearAccount()
    {
        $accounts = ['ACCESS_TOKEN', 'REFRESH_TOKEN', 'COOKIE'];
        foreach ($accounts as $account) {
            Config::put($account, '');
        }
    }

}