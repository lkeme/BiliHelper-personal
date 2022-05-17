<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Env;
use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class Silver
{
    use TimeLock;

    protected static array $task = [];

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time()) {
            return;
        }
        self::setPauseStatus();

        if (empty(self::$task)) {
            self::getSilverBox();
        } else {
            self::openSilverBox();
        }
    }

    /**
     * @use 获取宝箱
     */
    private static function getSilverBox(): void
    {
        $url = 'https://api.live.bilibili.com/lottery/v1/SilverBox/getCurrentTask';
        $payload = [];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);

        if (isset($data['code']) && $data['code'] == -10017) {
            Log::notice($data['message']);
            if (User::isMaster()) {
                self::setLock(self::timing(6));
            } else {
                self::setLock(self::timing(10));
            }
            return;
        }

        if (isset($data['code']) && $data['code']) {
            Env::failExit("check freeSilverCurrentTask failed! Error message: {$data['message']}");
        }

        Log::info("获得一个宝箱，内含 {$data['data']['silver']} 个瓜子");
        Log::info("开启宝箱需等待 {$data['data']['minute']} 分钟");

        self::$task = [
            'time_start' => $data['data']['time_start'],
            'time_end' => $data['data']['time_end'],
        ];
        self::setLock($data['data']['minute'] * 60 + 5);
    }


    /**
     * @use 开启宝箱
     */
    private static function openSilverBox(): void
    {
        $url = 'https://api.live.bilibili.com/mobile/freeSilverAward';
        $payload = [
            'time_end' => self::$task['time_end'],
            'time_start' => self::$task['time_start']
        ];
        $data = Curl::get('app', $url, Sign::common($payload));
        $data = json_decode($data, true);

        // {"code":400,"msg":"访问被拒绝","message":"访问被拒绝","data":[]}
        if (isset($data['msg']) && $data['code'] == 400 && $data['msg'] == '访问被拒绝') {
            self::pauseLock();
            return;
        }

        if ($data['code'] == -800) {
            self::setLock(12 * 60 * 60);
            Log::warning("领取宝箱失败，{$data['message']}!");
            return;
        }

        if ($data['code'] == -903) {
            Log::warning("领取宝箱失败，{$data['message']}!");
            self::$task = [];
            self::setLock(60);
            return;
        }

        if (isset($data['code']) && $data['code']) {
            Log::warning("领取宝箱失败，{$data['message']}!");
            self::setLock(60);
            return;
        }

        Log::notice("领取宝箱成功，Silver: {$data['data']['silver']}(+{$data['data']['awardSilver']})");

        self::$task = [];
        self::setLock(10);
    }
}
