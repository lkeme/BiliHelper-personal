<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace lkeme\BiliHelper;

class Silver2Coin
{
    public static $lock = 0;

    public static function run()
    {
        if (self::$lock > time() || getenv('USE_SILVER2COIN') == 'false') {
            return;
        }
        if (self::appSilver2coin() && self::pcSilver2coin()) {
            self::$lock = time() + 24 * 60 * 60;
            return;
        }
        self::$lock = time() + 3600;
    }


    // APP API
    protected static function appSilver2coin(): bool
    {
        sleep(1);
        $payload = [];
        $raw = Curl::get('https://api.live.bilibili.com/AppExchange/silver2coin', Sign::api($payload));
        $de_raw = json_decode($raw, true);

        if (!$de_raw['code'] && $de_raw['msg'] == '兑换成功') {
            Log::info('[APP]银瓜子兑换硬币: ' . $de_raw['msg']);
        } elseif ($de_raw['code'] == 403) {
            Log::info('[APP]银瓜子兑换硬币: ' . $de_raw['msg']);
        } else {
            Log::warning('[APP]银瓜子兑换硬币: ' . $de_raw['msg']);
            return false;
        }
        return true;
    }

    // PC API
    protected static function pcSilver2coin(): bool
    {
        sleep(1);
        $payload = [];
        $url = "https://api.live.bilibili.com/pay/v1/Exchange/silver2coin";
        $url1 = "https://api.live.bilibili.com/exchange/silver2coin";

        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == -403) {
            return false;
        }
        Log::info('[PC]银瓜子兑换硬币: ' . $de_raw['msg']);
        // TODO
        return true;
    }
}