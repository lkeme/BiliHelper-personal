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

namespace Bhp\Api\PassportTv;

use Bhp\Request\Request;
use Bhp\Sign\Sign;

class ApiQrcode
{

    /**
     * 获取authCode
     * @return array
     */
    public static function authCode(): array
    {
        $url = 'https://passport.bilibili.com/x/passport-tv-login/qrcode/auth_code';
        $payload = [];
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"url":"https://passport.bilibili.com/x/passport-tv-login/h5/qrcode/auth?auth_code=xxxx","auth_code":"xxxx"}}
        return Request::postJson(true, 'app', $url, Sign::login($payload), $headers);
    }

    /**
     * 验证登录
     * @param string $auth_code
     * @return mixed
     */
    public static function poll(string $auth_code): array
    {
        $url = 'https://passport.bilibili.com/x/passport-tv-login/qrcode/poll';
        $payload = [
            'auth_code' => $auth_code,
        ];
        $headers = [
            'content-type' => 'application/x-www-form-urlencoded',
        ];
        // {"code":0,"message":"0","ttl":1,"data":{"mid":123,"access_token":"xxx","refresh_token":"xxx","expires_in":2592000}}
        return Request::postJson(true, 'app', $url, Sign::login($payload), $headers);

    }

}
 