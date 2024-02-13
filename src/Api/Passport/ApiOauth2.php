<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2024 ~ 2025
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

class ApiOauth2
{
    /**
     * 获取令牌信息
     * @param string $token
     * @return array
     */
    public static function tokenInfo(string $token): array
    {
        $url = 'https://passport.bilibili.com/api/v2/oauth2/info';
        $payload = [
            'access_token' => $token,
        ];
        // {"ts":1234,"code":0,"data":{"mid":1234,"access_token":"1234","expires_in":7759292}}
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }

    /**
     * 新令牌信息
     * @param string $token
     * @return array
     */
    public static function tokenInfoNew(string $token): array
    {
        $url = 'https://passport.bilibili.com/x/passport-login/oauth2/info';
        $payload = [
            'access_key' => $token,
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"mid":"<user mid>","access_token":"<current token>","expires_in":9787360,"refresh":true}}
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }

    /**
     * 刷新令牌信息
     * @param string $token
     * @param string $r_token
     * @return array
     */
    public static function tokenRefreshNew(string $token, string $r_token): array
    {
        $url = 'https://passport.bilibili.com/x/passport-login/oauth2/refresh_token';
        $payload = [
            'access_key' => $token,
            'refresh_token' => $r_token,
        ];
        // {"message":"user not login","ts":1593111694,"code":-101}
        return Request::postJson(true, 'app', $url, Sign::login($payload));
    }

    /**
     * 刷新令牌信息
     * @param string $token
     * @param string $r_token
     * @return array
     */
    public static function tokenRefresh(string $token, string $r_token): array
    {
        $url = 'https://passport.bilibili.com/api/v2/oauth2/refresh_token';
        $payload = [
            'access_key' => $token,
            'refresh_token' => $r_token,
        ];
        // {"message":"user not login","ts":1593111694,"code":-101}
        return Request::postJson(true, 'app', $url, Sign::common($payload));
    }

    /**
     * 登录用户信息
     * @param string $token
     * @return array
     */
    public static function myInfo(string $token): array
    {
        $url = 'https://app.bilibili.com/x/v2/account/myinfo';
        $payload = [
            'access_key' => $token,
        ];
        return Request::getJson(true, 'app', $url, Sign::common($payload));
    }

    /**
     * 获取公钥
     * @return array
     */
    public static function getKey(): array
    {
        // $url = 'https://passport.bilibili.com/api/oauth2/getKey';
        $url = 'https://passport.bilibili.com/x/passport-login/web/key';
        $payload = [];
        return Request::getJson(true, 'app', $url, Sign::login($payload));
    }

    /**
     * 刷新COOKIE
     * @param string $token
     * @return array
     */
    public static function token2Cookie(string $token): array
    {
        $url = 'https://passport.bilibili.com/api/login/sso';
        $payload = [
            'access_key' => $token,
            'gourl' => 'https%3A%2F%2Faccount.bilibili.com%2Faccount%2Fhome'
        ];
        return Request::headers('app', $url, Sign::common($payload));
    }

}
