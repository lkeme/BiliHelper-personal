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

class StormRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '节奏风暴';
    const ACTIVE_SWITCH = 'USE_STORM';

    use TimeLock;

    protected static $wait_list = [];
    protected static $finish_list = [];
    protected static $all_list = [];

    private static $drop_rate = null;
    private static $attempt = null;


    /**
     * @use 解析数据
     * @param int $room_id
     * @param array $data
     * @return bool
     */
    protected static function parse(int $room_id, array $data): bool
    {
        // 防止异常
        if (!array_key_exists('storm', $data['data'])) {
            return false;
        }
        $de_raw = $data['data']['storm'];
        if (empty($de_raw)) {
            return false;
        }
        // 无效抽奖
        if ($de_raw['hadJoin'] != 0) {
            return false;
        }
        // 过滤抽奖范围
        self::$drop_rate = getenv('STORM_DROPRATE') !== "" ? (int)getenv('STORM_DROPRATE') : 0;
        if (mt_rand(1, 100) <= (int)self::$drop_rate) {
            return false;
        }
        // 去重
        if (self::toRepeatLid($de_raw['id'])) {
            return false;
        }
        // 推入列表
        $data = [
            'room_id' => $room_id,
            'raffle_id' => $de_raw['id'],
            'raffle_name' => '节奏风暴',
            'wait' => time()
        ];
        Statistics::addPushList(self::ACTIVE_TITLE);
        array_push(self::$wait_list, $data);
        return true;
    }


    /**
     * 格式化日志输出
     * @param $id
     * @param $num
     * @param $info
     * @return string
     */
    private static function formatInfo($id, $num, $info): string
    {
        return "风暴 {$id} 请求 {$num} 状态 {$info}";
    }


    /**
     * @use 请求抽奖
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    protected static function lottery(array $data): bool
    {
        self::$attempt = getenv('STORM_ATTEMPT') !== "" ? explode(',', getenv('STORM_ATTEMPT')) : [30, 50];
        $num = random_int((int)self::$attempt[0], (int)self::$attempt[1]);
        $user_info = User::parseCookies();
        $payload = [
            'id' => $data['raffle_id'],
            'roomid' => $data['room_id'],
            "color" => "16772431",
            "captcha_token" => "",
            "captcha_phrase" => "",
            "token" => $user_info['token'],
            "csrf_token" => $user_info['token'],
            "visit_id" => "",
        ];
        $url = 'https://api.live.bilibili.com/lottery/v1/Storm/join';
        for ($i = 1; $i < $num; $i++) {
            $raw = Curl::post($url, Sign::api($payload));
            $de_raw = json_decode($raw, true);
            if ($de_raw['code'] == 429 || $de_raw['code'] == -429) {
                Log::notice(self::formatInfo($data['raffle_id'], $num, '节奏风暴未实名或异常验证码'));
                break;
            }
            if (isset($de_raw['data']) && empty($de_raw['data'])) {
                Log::notice(self::formatInfo($data['raffle_id'], $num, '节奏风暴在小黑屋'));
                break;
            }
            if ($de_raw['code'] == 0) {
                Statistics::addSuccessList(self::ACTIVE_TITLE);
                Log::notice(self::formatInfo($data['raffle_id'], $num, $de_raw['data']['mobile_content']));
                break;
            }
            if ($de_raw['msg'] == '节奏风暴不存在') {
                Log::notice(self::formatInfo($data['raffle_id'], $num, '节奏风暴已结束'));
                break;
            }
            if ($de_raw['msg'] == '已经领取奖励') {
                Log::notice(self::formatInfo($data['raffle_id'], $num, '节奏风暴已经领取'));
                break;
            }
            if ($de_raw['msg'] == '你错过了奖励，下次要更快一点哦~') {
                continue;
            }
            Log::notice(self::formatInfo($data['raffle_id'], $num, $de_raw['msg']));
            continue;
        }
        return true;
    }
}
