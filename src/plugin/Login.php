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

    // 账密
    private static $username;
    private static $password;

    public static function run()
    {
        if (self::getLock()) {
            self::keepAuth();
            return;
        }
        Log::info('启动登录程序');
        if (getenv('ACCESS_TOKEN') == "") {
            Log::info('准备载入登录令牌');
            self::login();
        }

        Log::info('检查登录令牌有效性');
        if (!self::checkToken()) {
            Log::warning('登录令牌即将过期');
            Log::info('申请更换登录令牌中');
            if (!self::refreshToken()) {
                Log::warning('无效的登录令牌，尝试重新申请');
                self::login();
            }
        }
        self::setLock(3600);
    }


    /**
     * @use 登录控制中心
     */
    private static function login()
    {
        self::checkLogin();
        switch (intval(getenv('LOGIN_MODE'))) {
            case 1:
                // 账密模式
                self::accountLogin();
                break;
            case 2:
                // 短信验证码模式
                self::smsLogin();
                break;
            case 3:
                // 行为验证码模式(暂未开放)
                // self::captchaLogin();
                Log::error('此登录模式暂未开放');
                die();
                break;
            default:
                Log::error('登录模式配置错误');
                die();
        }
    }

    /**
     * @use 检查登录
     */
    private static function checkLogin()
    {
        $user = getenv('APP_USER');
        $pass = getenv('APP_PASS');
        if (empty($user) || empty($pass)) {
            Log::error('空白的帐号和口令');
            die();
        }
        self::clearAccount();
        self::$username = $user;
        self::$password = self::publicKeyEnc($pass);
    }


    /**
     * @use 保持认证
     * @return bool
     */
    private static function keepAuth()
    {
        if (self::getLock() > time()) {
            return true;
        }
        self::setLock(7200);
        if (!self::checkToken()) {
            Log::warning('令牌即将过期');
            Log::info('申请更换令牌中...');
            if (!self::refreshToken()) {
                Log::warning('无效令牌，正在重新申请...');
                self::accountLogin();
            }
            return false;
        }
        return true;
    }

    /**
     * @use 获取令牌信息
     * @return bool
     */
    private static function checkToken()
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
     * @use 刷新Token
     */
    private static function refreshToken()
    {
        $url = 'https://passport.bilibili.com/api/v2/oauth2/refresh_token';
        $payload = [
            'access_token' => getenv('ACCESS_TOKEN'),
            'refresh_token' => getenv('REFRESH_TOKEN'),
        ];
        $raw = Curl::post('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);
        // {"message":"user not login","ts":1593111694,"code":-101}
        if (isset($de_raw['code']) && $de_raw['code']) {
            Log::error('重新生成令牌失败', ['msg' => $de_raw['message']]);
            return false;
        }
        Log::info('重新令牌生成完毕');
        $access_token = $de_raw['data']['token_info']['access_token'];
        $refresh_token = $de_raw['data']['token_info']['refresh_token'];
        self::saveConfig('ACCESS_TOKEN', $access_token);
        self::saveConfig('REFRESH_TOKEN', $refresh_token);
        self::saveCookie($de_raw);
        Log::info('重置信息配置完毕');
        return true;
    }

    /**
     * @use 检查Cookie
     */
    private static function checkCookie()
    {

    }


    /**
     * @use 刷新Cookie
     */
    private static function refreshCookie()
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
     * @use 公钥加密
     * @param $plaintext
     * @return string
     */
    private static function publicKeyEnc($plaintext): string
    {
        Log::info('正在载入公钥');
        $url = 'https://passport.bilibili.com/api/oauth2/getKey';
        $payload = [];
        $data = Curl::post('app', $url, Sign::login($payload));
        $data = json_decode($data, true);
        if (isset($data['code']) && $data['code']) {
            Log::error('公钥载入失败', ['msg' => $data['message']]);
            die();
        } else {
            Log::info('公钥载入完毕');
        }
        $public_key = $data['data']['key'];
        $hash = $data['data']['hash'];
        openssl_public_encrypt($hash . $plaintext, $crypt, $public_key);
        return base64_encode($crypt);
    }


    /**
     * @use 获取验证码
     * @return array|string[]
     */
    private static function getCaptcha(): array
    {
        $url = 'https://passport.bilibili.com/web/captcha/combine';
        $payload = [
            'plat' => 3
        ];
        $raw = Curl::get('other', $url, $payload);
        $de_raw = json_decode($raw, true);
        // {"code":0,"data":{"result":{"success":1,"gt":"b6e5b7fad7ecd37f465838689732e788","challenge":"88148a764f94e5923564b356a69277fc","key":"230509df5ce048ca9bf29e1ee323af30"},"type":1}}
        Log::info('正在获取验证码 ' . $de_raw['code']);
        if ($de_raw['code'] == 0 && isset($de_raw['data']['result'])) {
            return [
                'gt' => $de_raw['data']['result']['gt'],
                'challenge' => $de_raw['data']['result']['challenge'],
                'key' => $de_raw['data']['result']['key'],
            ];
        }
        return [
            'gt' => '',
            'challenge' => '',
            'key' => ''
        ];
    }

    /**
     * @use 识别验证码
     * @param array $captcha
     * @return array
     */
    private static function ocrCaptcha(array $captcha): array
    {
        $url = 'http://captcha-v1.mudew.com:19951/';
        $payload = [
            'type' => 'gt3',
            'gt' => $captcha['gt'],
            "challenge" => $captcha['challenge'],
            "referer" => "https://passport.bilibili.com/"
        ];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $data = Curl::post('other', $url, $payload, $headers);
        $de_raw = json_decode($data, true);
        Log::info('正在获取验证码 ' . $de_raw['code']);
        return [
            'validate' => $de_raw['data']['validate'],
            'challenge' => $de_raw['data']['challenge']
        ];
    }


    /**
     * @use 验证码登录
     * @param string $mode
     */
    private static function captchaLogin(string $mode = '验证码模式')
    {
        $captcha_ori = self::getCaptcha();
        $captcha = self::ocrCaptcha($captcha_ori);
        self::accountLogin($captcha['validate'], $captcha['challenge'], $mode);
    }

    /**
     * @use 账密登录
     * @param string $validate
     * @param string $challenge
     * @param string $mode
     */
    private static function accountLogin(string $validate = '', string $challenge = '', string $mode = '账密模式')
    {
        Log::info("尝试{$mode}登录");
        $url = 'https://passport.bilibili.com/api/v3/oauth2/login';
        $payload = [
            'seccode' => $validate ? "{$validate}|jordan" : '',
            'validate' => $validate,
            'challenge' => $challenge,
            'permission' => 'ALL',
            'username' => self::$username,
            'password' => self::$password,
            'captcha' => '',
            'subid' => 1,
            'cookies' => ''
        ];
        $raw = Curl::post('app', $url, Sign::login($payload));
        $de_raw = json_decode($raw, true);
        // {"ts":1593079322,"code":-629,"message":"账号或者密码错误"}
        // {"ts":1593082268,"code":-105,"data":{"url":"https://passport.bilibili.com/register/verification.html?success=1&gt=b6e5b7fad7ecd37f465838689732e788&challenge=7efb4020b22c0a9ac124aea624e11ad7&ct=1&hash=7fa8282ad93047a4d6fe6111c93b308a"},"message":"验证码错误"}
        // {"ts":1593082432,"code":0,"data":{"status":0,"token_info":{"mid":123456,"access_token":"123123","refresh_token":"123123","expires_in":2592000},"cookie_info":{"cookies":[{"name":"bili_jct","value":"123123","http_only":0,"expires":1595674432},{"name":"DedeUserID","value":"123456","http_only":0,"expires":1595674432},{"name":"DedeUserID__ckMd5","value":"123123","http_only":0,"expires":1595674432},{"name":"sid","value":"bd6aagp7","http_only":0,"expires":1595674432},{"name":"SESSDATA","value":"6d74d850%123%2Cf0e36b61","http_only":1,"expires":1595674432}],"domains":[".bilibili.com",".biligame.com",".bigfunapp.cn"]},"sso":["https://passport.bilibili.com/api/v2/sso","https://passport.biligame.com/api/v2/sso","https://passport.bigfunapp.cn/api/v2/sso"]}}
        // https://passport.bilibili.com/mobile/verifytel_h5.html
        switch ($de_raw['code']) {
            case 0:
                // 正常登录
                Log::info("{$mode}登录成功");
                $access_token = $de_raw['data']['token_info']['access_token'];
                $refresh_token = $de_raw['data']['token_info']['refresh_token'];
                self::saveConfig('ACCESS_TOKEN', $access_token);
                self::saveConfig('REFRESH_TOKEN', $refresh_token);
                self::saveCookie($de_raw);
                Log::info('信息配置完毕');
                break;
            case -105:
                // 需要验证码
                Log::error('登录失败', ['msg' => '此次登录需要验证码或' . $de_raw['message']]);
                die();
                break;
            case -629:
                // 密码错误
                Log::error('登录失败', ['msg' => $de_raw['message']]);
                die();
                break;
            case  -2100:
                // 验证手机号
                Log::error('登录失败', ['msg' => '账号启用了设备锁或异地登录需验证手机号']);
                die();
                break;
            default:
                Log::error('登录失败', ['msg' => '未知错误: ' . $de_raw['message']]);
                die();
                break;
        }
    }

    /**
     * @use 短信登录
     * @param string $mode
     */
    private static function smsLogin(string $mode = '短信模式')
    {
        Log::info("尝试{$mode}登录");
        self::checkPhone(self::$username);
        $captcha = self::sendSms(self::$username);
        $url = 'https://passport.bilibili.com/x/passport-login/login/sms';
        $payload = [
            'captcha_key' => $captcha['captcha_key'],
            'cid' => $captcha['cid'],
            'tel' => $captcha['tel'],
            'statistics' => $captcha['statistics'],
            'code' => self::cliInput('请输入收到的短信验证码: '),
        ];
        $raw = Curl::post('app', $url, Sign::login($payload));
        $de_raw = json_decode($raw, true);
        switch ($de_raw['code']) {
            case 0:
                // 正常登录
                Log::info("{$mode}登录成功");
                $access_token = $de_raw['data']['token_info']['access_token'];
                $refresh_token = $de_raw['data']['token_info']['refresh_token'];
                self::saveConfig('ACCESS_TOKEN', $access_token);
                self::saveConfig('REFRESH_TOKEN', $refresh_token);
                self::saveCookie($de_raw);
                Log::info('信息配置完毕');
                break;
            case -105:
                // 需要验证码
                Log::error('登录失败', ['msg' => '此次登录需要验证码或' . $de_raw['message']]);
                die();
                break;
            case -629:
                // 密码错误
                Log::error('登录失败', ['msg' => $de_raw['message']]);
                die();
                break;
            case  -2100:
                // 验证手机号
                Log::error('登录失败', ['msg' => '账号启用了设备锁或异地登录需验证手机号']);
                die();
                break;
            default:
                Log::error('登录失败', ['msg' => '未知错误: ' . $de_raw['message']]);
                die();
                break;
        }

    }

    /**
     * @use 输入短信验证码
     * @param string $msg
     * @param int $max_char
     * @return string
     */
    private static function cliInput(string $msg, $max_char = 100): string
    {
        $stdin = fopen('php://stdin', 'r');
        echo '# ' . $msg;
        $input = fread($stdin, $max_char);
        fclose($stdin);
        return str_replace(PHP_EOL, '', $input);
    }

    /**
     * @use 发送短信验证码
     * @param string $phone
     * @return array
     */
    private static function sendSms(string $phone): array
    {
        $url = 'https://passport.bilibili.com//x/passport-login/sms/send';
        $payload = [
            'cid' => '86',
            'tel' => $phone,
            'statistics' => '{"appId":1,"platform":3,"version":"6.3.0","abtest":""}',
        ];
        $raw = Curl::post('app', $url, Sign::login($payload));
        $de_raw = json_decode($raw, true);
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"4e292933816755442c1568e2043b8e41","recaptcha_url":""}}
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"","recaptcha_url":"https://www.bilibili.com/h5/project-msg-auth/verify?ct=geetest\u0026recaptcha_token=ad520c3a4a3c46e29b1974d85efd2c4b\u0026gee_gt=1c0ea7c7d47d8126dda19ee3431a5f38\u0026gee_challenge=c772673050dce482b9f63ff45b681ceb\u0026hash=ea2850a43cc6b4f1f7b925d601098e5e"}}
        if ($de_raw['code'] == 0 && isset($de_raw['data']['captcha_key']) && $de_raw['data']['recaptcha_url'] == '') {
            Log::info("短信验证码发送成功 {$de_raw['data']['captcha_key']}");
            $payload['captcha_key'] = $de_raw['data']['captcha_key'];
            return $payload;
        }
        Log::error("短信验证码发送失败 {$raw}");
        die();
    }

    /**
     * @use 检查手机号格式
     * @param string $phone
     */
    private static function checkPhone(string $phone)
    {
        if (!preg_match("/^1[3456789]{1}\d{9}$/", $phone)) {
            Log::error("当前用户名不是有效手机号格式");
            die();
        }
    }

    /**
     * @use 保存配置
     * @param string $key
     * @param string $value
     * @param bool $hide
     */
    private static function saveConfig(string $key, string $value, $hide = true)
    {
        Config::put($key, $value);
        Log::info(" > {$key}: " . ($hide ? substr_replace($value, '********', mb_strlen($value) / 2, 8) : $value));
    }

    /**
     * @use 保存配置
     * @param array $data
     */
    private static function saveCookie(array $data)
    {
        $c = '';
        $cookies = $data['data']['cookie_info']['cookies'];
        foreach ($cookies as $cookie) {
            $c .= $cookie['name'] . '=' . $cookie['value'] . ';';
        }
        self::saveConfig('COOKIE', $c);
    }

    /**
     * @use 清除已有
     */
    private static function clearAccount()
    {
        $variables = ['ACCESS_TOKEN', 'REFRESH_TOKEN', 'COOKIE'];
        foreach ($variables as $variable) {
            Config::put($variable, '');
        }
    }


}