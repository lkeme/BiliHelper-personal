<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
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

class ApiCaptcha
{
    /**
     * 获取验证码
     * @param int $plat
     * @return mixed
     */
    public static function combine(int $plat = 3): array
    {
        $url = 'https://passport.bilibili.com/web/captcha/combine';
        $payload = [
            'plat' => $plat
        ];
        // {"code":0,"data":{"result":{"success":1,"gt":"b6e5b7fad7ecd37f465838689732e788","challenge":"88148a764f94e5923564b356a69277fc","key":"230509df5ce048ca9bf29e1ee323af30"},"type":1}}

        return Request::getJson(true, 'other', $url, $payload);
    }

    /**
     * 识别验证码
     * @param array $captcha
     * @return array
     */
    public static function ocr(array $captcha):array{
        $url = 'https://captcha-v1.mudew.com:19951/';
        $payload = [
            'type' => 'gt3',
            'gt' => $captcha['gt'],
            "challenge" => $captcha['challenge'],
            "referer" => "https://passport.bilibili.com/"
        ];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        return Request::postJson(true,'other', $url, $payload, $headers);
    }

    /**
     * @param string $challenge
     * @return array
     */
    public static function fetch(string $challenge): array
    {
        $url = getConf('login_captcha.url') . '/fetch';
        $payload = [
            'challenge' => $challenge,
        ];
        return Request::getJson(true, 'other', $url, $payload);
    }
}
