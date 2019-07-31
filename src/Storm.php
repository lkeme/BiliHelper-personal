<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 *  LastAPIChecked: 20190731
 */

namespace lkeme\BiliHelper;

class Storm
{
    const ACTIVE_TITLE = '节奏风暴';
    const ACTIVE_SWITCH = 'USE_STORM';

    public static $lock = 0;

    protected static $wait_list = [];
    protected static $all_list = [];

    private static $drop_rate = null;
    private static $attempt = null;

    /**
     * @throws \Exception
     */
    public static function run()
    {
        if (getenv(self::ACTIVE_SWITCH) == 'false') {
            return;
        }
        if (self::$lock > time()) {
            return;
        }
        self::init();
        self::startLottery();
    }

    private static function init()
    {
        if (!is_null(self::$attempt) && !is_null(self::$drop_rate)) {
            return;
        }
        // TODO 信息不完整, 请检查配置文件 没有严格校验
        self::$drop_rate = getenv('STORM_DROPRATE') !== "" ? (int)getenv('STORM_DROPRATE') : 0;
        self::$attempt = getenv('STORM_ATTEMPT') !== "" ? explode(',', getenv('STORM_ATTEMPT')) : [30, 50];
    }

    /**
     * 抽奖逻辑
     * @return bool
     * @throws \Exception
     */
    protected static function startLottery(): bool
    {
        while (true) {
            $storm = array_shift(self::$wait_list);
            if (is_null($storm)) {
                break;
            }
            // 丢弃
            if (mt_rand(1, 100) <= (int)self::$drop_rate) {
                break;
            }
            $storm_lid = $storm['lid'];
            $storm_rid = $storm['rid'];
            Live::goToRoom($storm_rid);
            Statistics::addJoinList(self::ACTIVE_TITLE);
            $num = random_int((int)self::$attempt[0], (int)self::$attempt[1]);
            for ($i = 1; $i < $num; $i++) {
                if (!self::lottery($storm_rid, $storm_lid, $i)) {
                    break;
                }
            }
        }
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
     * 请求抽奖
     * @param $rid
     * @param $lid
     * @param $num
     * @return bool
     */
    protected static function lottery($rid, $lid, $num): bool
    {
        $user_info = User::parseCookies();
        $payload = [
            "id" => $lid,
            "color" => "16772431",
            "roomid" => $rid,
            "captcha_token" => "",
            "captcha_phrase" => "",
            "token" => $user_info['token'],
            "csrf_token" => $user_info['token'],
            "visit_id" => "",
        ];
        $raw = Curl::post('https://api.live.bilibili.com/lottery/v1/Storm/join', Sign::api($payload));
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 429 || $de_raw['code'] == -429) {
            Log::notice(self::formatInfo($lid, $num, '节奏风暴未实名或异常验证码'));
            return false;
        }
        if (isset($de_raw['data']) && empty($de_raw['data'])) {
            Log::notice(self::formatInfo($lid, $num, '节奏风暴在小黑屋'));
            return false;
        }
        if ($de_raw['code'] == 0) {
            Statistics::addSuccessList(self::ACTIVE_TITLE);
            Log::notice(self::formatInfo($lid, $num, $de_raw['data']['mobile_content']));
            return false;
        }
        if ($de_raw['msg'] == '节奏风暴不存在') {
            Log::notice(self::formatInfo($lid, $num, '节奏风暴已结束'));
            return false;
        }
        if ($de_raw['msg'] == '已经领取奖励') {
            Log::notice(self::formatInfo($lid, $num, '节奏风暴已经领取'));
            return false;
        }
        if ($de_raw['msg'] == '你错过了奖励，下次要更快一点哦~') {
            return true;
        }
        Log::notice(self::formatInfo($lid, $num, $de_raw['msg']));
        return true;
    }

    /**
     * 检查ID
     * @param $room_id
     * @return array|bool
     */
    protected static function stormCheckId($room_id)
    {
        $raw = Curl::get('https://api.live.bilibili.com/lottery/v1/Storm/check?roomid=' . $room_id);
        $de_raw = json_decode($raw, true);

        if (empty($de_raw['data']) || $de_raw['data']['hasJoin'] != 0) {
            return false;
        }
        return [
            'id' => $de_raw['data']['id'],
        ];
    }


    /**
     * 重复检测
     * @param $lid
     * @return bool
     */
    private static function toRepeatLid($lid): bool
    {
        if (in_array($lid, self::$all_list)) {
            return true;
        }
        if (count(self::$all_list) > 2000) {
            self::$all_list = [];
        }
        array_push(self::$all_list, $lid);

        return false;
    }

    /**
     * 数据推入队列
     * @param array $data
     * @return bool
     */
    public static function pushToQueue(array $data): bool
    {
        if (getenv(self::ACTIVE_SWITCH) == 'false') {
            return false;
        }
        if (self::toRepeatLid($data['lid'])) {
            return false;
        }
        Statistics::addPushList(self::ACTIVE_TITLE);
        self::$wait_list = array_merge(self::$wait_list, [['rid' => $data['rid'], 'lid' => $data['lid']]]);
        $wait_num = count(self::$wait_list);
        if ($wait_num > 2) {
            Log::info("当前队列中共有 {$wait_num} 个" . self::ACTIVE_TITLE . "待抽奖");
        }
        return true;
    }
}
