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

namespace Bhp\Api\PassportTv;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

class ApiQrcode
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * 获取authCode
     * @return array
     */
    public function authCode(): array
    {
        $url = 'https://passport.bilibili.com/x/passport-tv-login/qrcode/auth_code';
        $payload = [];
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"url":"https://passport.bilibili.com/x/passport-tv-login/h5/qrcode/auth?auth_code=xxxx","auth_code":"xxxx"}}
        return $this->decodePost('app', $url, $this->request->signLoginPayload($payload), $headers, 'passport_tv.qrcode.auth_code');
    }

    /**
     * 验证登录
     * @param string $auth_code
     * @return mixed
     */
    public function poll(string $auth_code): array
    {
        $url = 'https://passport.bilibili.com/x/passport-tv-login/qrcode/poll';
        $payload = [
            'auth_code' => $auth_code,
        ];
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"mid":123,"access_token":"xxx","refresh_token":"xxx","expires_in":2592000}}
        return $this->decodePost('app', $url, $this->request->signLoginPayload($payload), $headers, 'passport_tv.qrcode.poll');

    }

    /**
     * 获取确认Url
     * @param string $app_key
     * @param string $app_secret
     * @return array
     */
    public function getConfrimUrl(string $app_key, string $app_secret): array
    {
        $sign = md5('api=http://link.acg.tv/forum.php' . $app_secret);
        //
        $url = 'https://passport.bilibili.com/login/app/third';
        $payload = [
            'appkey' => $app_key,
            'api' => 'http://link.acg.tv/forum.php',
            'sign' => $sign
        ];
        $headers = [
            "origin" => 'https://passport.bilibili.com',
            "referer" => 'https://passport.bilibili.com',
        ];
        return $this->decodeGet('pc', $url, $payload, $headers, 'passport_tv.qrcode.confirm_url');
    }

    /**
     * 跳转确认Url
     * @param string $url
     * @return array
     */
    public function goConfrimUrl(string $url): array
    {
        // 取出url的主体部分
        $query = parse_url($url)['query'];
        // 取出url参数部分转为数组
        parse_str($query, $payload);
        $headers = [
            "origin" => 'https://passport.bilibili.com',
            "referer" => 'https://passport.bilibili.com',
        ];
        return $this->request->fetchHeaders('pc', $url, $payload, $headers);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodeGet(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->getText($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, $label);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function decodePost(string $os, string $url, array $payload, array $headers, string $label): array
    {
        try {
            $raw = $this->request->postText($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, $label);
    }
}
