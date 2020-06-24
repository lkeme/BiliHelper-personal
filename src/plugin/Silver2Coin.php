<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
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
            self::setLock(self::timing(10));
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
        sleep(0.5);
        $url = 'https://api.live.bilibili.com/AppExchange/silver2coin';
        $payload = [];
        $raw = Curl::get('app', $url, Sign::common($payload));
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
        sleep(0.5);
        $payload = [];
        $url = "https://api.live.bilibili.com/exchange/silver2coin";
        $url = "https://api.live.bilibili.com/pay/v1/Exchange/silver2coin";

        $raw = Curl::get('pc', $url, $payload);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == -403) {
            return false;
        }
        Log::info('[PC]银瓜子兑换硬币: ' . $de_raw['msg']);
        return true;
    }
}