<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class Silver2Coin
{
    use TimeLock;

    public static function run()
    {
        if (self::getLock() > time() || getenv('USE_SILVER2COIN') == 'false') {
            return;
        }
        if (self::appSilver2coin() && self::pcSilver2coin()) {
            self::setLock(24 * 60 * 60);
            return;
        }
        self::setLock(3600);
    }


    /**
     * @use app兑换
     * @return bool
     */
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


    /**
     * @use pc兑换
     * @return bool
     */
    protected static function pcSilver2coin(): bool
    {
        sleep(1);
        $payload = [];
        $url = "https://api.live.bilibili.com/exchange/silver2coin";
        $url = "https://api.live.bilibili.com/pay/v1/Exchange/silver2coin";
        
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