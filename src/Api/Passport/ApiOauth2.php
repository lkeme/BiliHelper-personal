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

namespace Bhp\Api\Passport;

use Bhp\Api\Support\ApiJson;
use Bhp\Log\Log;
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
        return \Bhp\Api\Support\ApiJson::get( 'app', $url, Sign::common($payload));
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
        return ApiJson::get('app', $url, Sign::common($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.info.new');
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
        $response = ApiJson::post('app', $url, Sign::login($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.refresh_token.new');
        if (!self::isValidRefreshResponse($response)) {
            Log::warning('新登录令牌刷新接口响应异常，已自动回退旧刷新接口');
            return self::tokenRefresh($token, $r_token);
        }

        return $response;
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
        return ApiJson::post('app', $url, Sign::common($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.refresh_token.legacy');
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
        return ApiJson::get('app', $url, Sign::common($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.myinfo');
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
        return ApiJson::get('app', $url, Sign::login($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.get_key');
    }

    /**
     * 登录用户统计
     * @param string $token
     * @return array<string, mixed>
     */
    public static function navStat(string $token): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/nav/stat';
        $payload = [
            'access_key' => $token,
        ];

        return ApiJson::get('pc', $url, $payload, [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.nav_stat');
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

    /**
     * @param array<string, mixed> $response
     */
    protected static function isValidRefreshResponse(array $response): bool
    {
        if (($response['code'] ?? -1) !== 0) {
            return false;
        }

        return isset($response['data']) && is_array($response['data']);
    }

}
