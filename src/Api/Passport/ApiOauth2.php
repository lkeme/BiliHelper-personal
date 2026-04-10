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

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

class ApiOauth2 extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * 获取令牌信息
     * @param string $token
     * @return array
     */
    public function tokenInfo(string $token): array
    {
        $url = 'https://passport.bilibili.com/api/v2/oauth2/info';
        $payload = [
            'access_token' => $token,
        ];
        // {"ts":1234,"code":0,"data":{"mid":1234,"access_token":"1234","expires_in":7759292}}
        return $this->decodeGet('app', $url, $this->request()->signCommonPayload($payload), [], 'oauth2.info.legacy');
    }

    /**
     * 新令牌信息
     * @param string $token
     * @return array
     */
    public function tokenInfoNew(string $token): array
    {
        $url = 'https://passport.bilibili.com/x/passport-login/oauth2/info';
        $payload = [
            'access_key' => $token,
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"mid":"<user mid>","access_token":"<current token>","expires_in":9787360,"refresh":true}}
        return $this->decodeGet('app', $url, $this->request()->signCommonPayload($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.info.new');
    }

    /**
     * 刷新令牌信息
     * @param string $token
     * @param string $r_token
     * @return array
     */
    public function tokenRefreshNew(string $token, string $r_token): array
    {
        $url = 'https://passport.bilibili.com/x/passport-login/oauth2/refresh_token';
        $payload = [
            'access_key' => $token,
            'refresh_token' => $r_token,
        ];
        // {"message":"user not login","ts":1593111694,"code":-101}
        $response = $this->decodePost('app', $url, $this->request()->signLoginPayload($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.refresh_token.new');
        if (!self::isValidRefreshResponse($response)) {
            return $this->tokenRefresh($token, $r_token);
        }

        return $response;
    }

    /**
     * 刷新令牌信息
     * @param string $token
     * @param string $r_token
     * @return array
     */
    public function tokenRefresh(string $token, string $r_token): array
    {
        $url = 'https://passport.bilibili.com/api/v2/oauth2/refresh_token';
        $payload = [
            'access_key' => $token,
            'refresh_token' => $r_token,
        ];
        // {"message":"user not login","ts":1593111694,"code":-101}
        return $this->decodePost('app', $url, $this->request()->signCommonPayload($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.refresh_token.legacy');
    }

    /**
     * 登录用户信息
     * @param string $token
     * @return array
     */
    public function myInfo(string $token): array
    {
        $url = 'https://app.bilibili.com/x/v2/account/myinfo';
        $payload = [
            'access_key' => $token,
        ];
        return $this->decodeGet('app', $url, $this->request()->signCommonPayload($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.myinfo');
    }

    /**
     * 获取公钥
     * @return array
     */
    public function getKey(): array
    {
        // $url = 'https://passport.bilibili.com/api/oauth2/getKey';
        $url = 'https://passport.bilibili.com/x/passport-login/web/key';
        $payload = [];
        return $this->decodeGet('app', $url, $this->request()->signLoginPayload($payload), [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.get_key');
    }

    /**
     * 登录用户统计
     * @param string $token
     * @return array<string, mixed>
     */
    public function navStat(string $token): array
    {
        $url = 'https://api.bilibili.com/x/web-interface/nav/stat';
        $payload = [
            'access_key' => $token,
        ];

        return $this->decodeGet('pc', $url, $payload, [
            'Accept-Encoding' => 'identity',
        ], 'oauth2.nav_stat');
    }

    /**
     * 刷新COOKIE
     * @param string $token
     * @return array
     */
    public function token2Cookie(string $token): array
    {
        $url = 'https://passport.bilibili.com/api/login/sso';
        $payload = [
            'access_key' => $token,
            'gourl' => 'https%3A%2F%2Faccount.bilibili.com%2Faccount%2Fhome'
        ];
        return $this->request()->fetchHeaders('app', $url, $this->request()->signCommonPayload($payload));
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
