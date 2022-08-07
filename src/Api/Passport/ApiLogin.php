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

namespace Bhp\Api\Passport;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiLogin
{
    /**
     * 密码登录
     * @param string $username
     * @param string $password
     * @param string $validate
     * @param string $challenge
     * @return array
     */
    public static function passwordLogin(string $username, string $password, string $validate = '', string $challenge = ''): array
    {
        // $url = 'https://passport.bilibili.com/api/v3/oauth2/login';
        $url = 'https://passport.bilibili.com/x/passport-login/oauth2/login';
        $payload = [
            'seccode' => $validate ? "$validate|jordan" : '',
            'validate' => $validate,
            'challenge' => $challenge,
            'permission' => 'ALL',
            'username' => $username,
            'password' => $password,
            'captcha' => '',
            'subid' => 1,
            'cookies' => ''
        ];
        return Request::postJson(true, 'app', $url, Sign::login($payload));
    }

    /**
     * 发送短信验证码
     * @param string $phone
     * @param string $cid
     * @return string
     */
    public static function sendSms(string $phone, string $cid): string
    {
        $url = 'https://passport.bilibili.com//x/passport-login/sms/send';
        // TODO 动态版本参数
        $payload = [
            'cid' => $cid,
            'tel' => $phone,
            'statistics' => '{"appId":1,"platform":3,"version":"6.83.0","abtest":""}',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"4e292933816755442c1568e2043b8e41","recaptcha_url":""}}
        // {"code":0,"message":"0","ttl":1,"data":{"is_new":false,"captcha_key":"","recaptcha_url":"https://www.bilibili.com/h5/project-msg-auth/verify?ct=geetest\u0026recaptcha_token=ad520c3a4a3c46e29b1974d85efd2c4b\u0026gee_gt=1c0ea7c7d47d8126dda19ee3431a5f38\u0026gee_challenge=c772673050dce482b9f63ff45b681ceb\u0026hash=ea2850a43cc6b4f1f7b925d601098e5e"}}
        return Request::post('app', $url, Sign::login($payload));
    }

    /**
     * 短信验证码登录
     * @param array $captcha
     * @param string $code
     * @return array
     */
    public static function smsLogin(array $captcha, string $code): array
    {
        $url = 'https://passport.bilibili.com/x/passport-login/login/sms';
        $payload = [
            'captcha_key' => $captcha['captcha_key'],
            'cid' => $captcha['cid'],
            'tel' => $captcha['tel'],
            'statistics' => $captcha['statistics'],
            'code' => $code,
        ];
        return Request::postJson(true, 'app', $url, Sign::login($payload));
    }

}
 