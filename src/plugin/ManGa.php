<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class ManGa
{
    use TimeLock;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('manga')) {
            return;
        }
        if (self::sign() && self::share()) {
            self::setLock(self::timing(10));
            return;
        }
        self::setLock(3600);
    }


    /**
     * @use 漫画签到
     * @return bool
     */
    private static function sign(): bool
    {
        sleep(1);
        $url = 'https://manga.bilibili.com/twirp/activity.v1.Activity/ClockIn';
        $payload = [
            'access_key' => getAccessToken(),
            'ts' => time()
        ];
        $raw = Curl::post('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);
        # {"code":0,"msg":"","data":{}}
        # {"code":"invalid_argument","msg":"clockin clockin is duplicate","meta":{"argument":"clockin"}}
        if (!$de_raw['code']) {
            Log::notice('漫画签到: 成功');
        } else {
            Log::warning('漫画签到: 失败或者重复操作');
        }
        return true;
    }


    /**
     * @use 漫画分享
     * @return bool
     */
    private static function share(): bool
    {
        sleep(1);
        $payload = [];
        $url = "https://manga.bilibili.com/twirp/activity.v1.Activity/ShareComic";
        $raw = Curl::post('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);
        # {"code":0,"msg":"","data":{"point":5}}
        # {"code":1,"msg":"","data":{"point":0}}
        if (!$de_raw['code']) {
            Log::notice('漫画分享: 成功');
        } else {
            Log::warning('漫画分享: 失败或者重复操作');
        }
        return true;
    }
}