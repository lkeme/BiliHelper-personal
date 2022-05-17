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

class Silver2Coin
{
    use TimeLock;

    /**
     * @use run
     */
    public static function run(): void
    {
        if (self::getLock() > time() || !getEnable('silver2coin')) {
            return;
        }
        if (self::appSilver2coin() && self::pcSilver2coin()) {
            // 定时10点 + 1-60分钟随机
            self::setLock(self::timing(10, 0, 0, true));
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
        usleep(0.5 * APP_MICROSECOND);
        $url = 'https://api.live.bilibili.com/AppExchange/silver2coin';
        $payload = [];
        $raw = Curl::post('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);

        return self::handle('APP', $de_raw);

    }

    /**
     * @use pc兑换
     * @return bool
     */
    protected static function pcSilver2coin(): bool
    {
        usleep(0.5 * APP_MICROSECOND);
        $payload = [
            'csrf_token' => getCsrf(),
            'csrf' => getCsrf(),
            'visit_id' => ''
        ];
        // $url = "https://api.live.bilibili.com/exchange/silver2coin";
        // $url = "https://api.live.bilibili.com/pay/v1/Exchange/silver2coin";
        $url = "https://api.live.bilibili.com/xlive/revenue/v1/wallet/silver2coin";
        $raw = Curl::post('pc', $url, $payload);
        $de_raw = json_decode($raw, true);

        return self::handle('PC', $de_raw);
    }

    /**
     * @use 处理结果
     * @param string $type
     * @param array $data
     * @return bool
     */
    private static function handle(string $type, array $data): bool
    {
        // {"code":403,"msg":"每天最多能兑换 1 个","message":"每天最多能兑换 1 个","data":[]}
        // {"code":403,"msg":"仅主站正式会员以上的用户可以兑换","message":"仅主站正式会员以上的用户可以兑换","data":[]}
        // {"code":0,"msg":"兑换成功","message":"兑换成功","data":{"gold":"5074","silver":"36734","tid":"727ab65376a15a6b117cf560a20a21122334","coin":1}}
        // {"code":0,"data":{"coin":1,"gold":1234,"silver":4321,"tid":"Silver2Coin21062316490299678123456"},"message":"兑换成功"}
        switch ($data['code']) {
            case 0:
                Log::notice("[$type] 银瓜子兑换硬币: {$data['message']}");
                return true;
            case 403:
                Log::warning("[$type] 银瓜子兑换硬币: {$data['message']}");
                return true;
            default:
                Log::warning("[$type] 银瓜子兑换硬币: CODE -> {$data['code']} MSG -> {$data['message']} ");
                return false;
        }
    }
}