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
    protected static function parseLotteryInfo(int $room_id, array $data): bool
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
        Statistics::addPushList($data['raffle_name']);
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
     * @use 创建抽奖任务
     * @param array $raffles
     * @return array
     */
    protected static function createLottery(array $raffles): array
    {
        $url = 'https://api.live.bilibili.com/lottery/v1/Storm/join';
        $user_info = User::parseCookies();
        foreach ($raffles as $raffle) {
            self::$attempt = getenv('STORM_ATTEMPT') !== "" ? explode(',', getenv('STORM_ATTEMPT')) : [30, 50];
            $num = mt_rand((int)self::$attempt[0], (int)self::$attempt[1]);
            $payload = [
                'id' => $raffle['raffle_id'],
                'roomid' => $raffle['room_id'],
                "color" => "16772431",
                "captcha_token" => "",
                "captcha_phrase" => "",
                "token" => $user_info['token'],
                "csrf_token" => $user_info['token'],
                "visit_id" => ""
            ];
            for ($i = 1; $i < $num; $i++) {
                $raw = Curl::post('app', $url, Sign::common($payload));
                if (strpos((string)$raw, 'html') !== false) {
                    Log::notice(self::formatInfo($raffle['raffle_id'], $num, '触发哔哩哔哩安全风控策略(412)'));
                    break;
                }
                $de_raw = json_decode($raw, true);
                // {"code":-412,"message":"请求被拦截","ttl":1,"data":null}
                if ($de_raw['code'] == -412) {
                    Log::notice(self::formatInfo($raffle['raffle_id'], $num, '触发哔哩哔哩安全风控策略(-412)'));
                    break;
                }
                if ($de_raw['code'] == 429 || $de_raw['code'] == -429) {
                    Log::notice(self::formatInfo($raffle['raffle_id'], $num, '节奏风暴未实名或异常验证码'));
                    break;
                }
                if ($de_raw['code'] == 0) {
                    $data = $de_raw['data'];
                    Statistics::addSuccessList($raffle['raffle_name']);
                    Log::notice(self::formatInfo($raffle['raffle_id'], $num, $data['mobile_content']));
                    Statistics::addProfitList($data['title'] . '-' . $data['gift_name'], $data['gift_num']);
                    break;
                }
                if (!isset($de_raw['msg'])) {
                    Log::notice(self::formatInfo($raffle['raffle_id'], $num, $de_raw));
                    break;
                }
                if ($de_raw['msg'] == '节奏风暴不存在' || $de_raw['msg'] == '节奏风暴抽奖过期' || $de_raw['msg'] == '没抢到') {
                    Log::notice(self::formatInfo($raffle['raffle_id'], $num, '节奏风暴已经结束'));
                    break;
                }
                if ($de_raw['msg'] == '已经领取奖励') {
                    Log::notice(self::formatInfo($raffle['raffle_id'], $num, '节奏风暴已经领取'));
                    break;
                }
                if (isset($de_raw['data']) && empty($de_raw['data'])) {
                    Log::debug(self::formatInfo($raffle['raffle_id'], $num, '节奏风暴在小黑屋'));
                    self::pauseLock();
                    break;
                }
                if ($de_raw['msg'] == '你错过了奖励，下次要更快一点哦~') {
                    continue;
                }
                Log::notice(self::formatInfo($raffle['raffle_id'], $num, $de_raw['msg']));
                continue;
            }
        }
        return [];
    }

    /**
     * @use 解析抽奖信息
     * @param array $results
     * @return mixed|void
     */
    protected static function parseLottery(array $results)
    {
        foreach ($results as $result) {
            $data = $result['source'];
            $content = $result['content'];
            echo '';
        }
    }
}
