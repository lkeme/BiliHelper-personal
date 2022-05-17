<?php


/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Util;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Plugin\Live;
use BiliHelper\Plugin\Sign;
use BiliHelper\Plugin\Statistics;

abstract class BaseRaffle
{
    use TimeLock;
    use FilterWords;

    const ACTIVE_TITLE = '';
    const ACTIVE_SWITCH = '';

    protected static array $wait_list;
    protected static array $finish_list;
    protected static array $all_list;
    protected static array $banned_rids = [];

    /**
     * @use run
     */
    public static function run(): void
    {
        if (!getEnable(static::ACTIVE_SWITCH)) {
            return;
        }
        if (static::getLock() > time()) {
            return;
        }
        static::setPauseStatus();
        static::loadJsonData();
        static::startLottery();
    }

    /**
     * @use 抽奖逻辑
     * @return bool
     */
    protected static function startLottery(): bool
    {
        if (count(static::$wait_list) == 0) {
            return false;
        }
        $raffle_list = [];
        $room_list = [];
        static::$wait_list = static::arrKeySort(static::$wait_list, 'wait');
        $max_num = count(static::$wait_list);
        for ($i = 0; $i <= $max_num; $i++) {
            $raffle = array_shift(static::$wait_list);
            if (is_null($raffle)) {
                break;
            }
            if ($raffle['wait'] > time()) {
                static::$wait_list[] = $raffle;
                break;
            }
            if (count($raffle_list) > 200) {
                break;
            }
            $room_list[] = $raffle['room_id'];
            $raffle_list[] = $raffle;
            // 有备注要单独处理
            if (isset($raffle['remarks'])) {
                Statistics::addJoinList($raffle['remarks']);
            } else {
                Statistics::addJoinList($raffle['raffle_name']);
            }
        }
        if (count($raffle_list) && count($room_list)) {
            $room_list = array_unique($room_list);
            foreach ($room_list as $room_id) {
                Live::goToRoom($room_id);
            }
            $results = static::createLottery($raffle_list);
            static::parseLottery($results);
        }
        return true;
    }

    /**
     * @use 返回抽奖数据
     * @param int $room_id
     * @return array
     */
    protected static function check(int $room_id): array
    {
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/lottery/getLotteryInfo';
        $payload = [
            'roomid' => $room_id
        ];
        $raw = Curl::get('app', $url, Sign::common($payload));
        $de_raw = json_decode($raw, true);
        if (!isset($de_raw['data']) || $de_raw['code']) {
            // Todo 请求被拦截 412
            Log::error("获取抽奖数据错误，{$de_raw['message']}");
            return [];
        }
        return $de_raw;
    }

    /**
     * @use 解析抽奖信息
     * @param int $room_id
     * @param array $data
     * @return bool
     */
    abstract protected static function parseLotteryInfo(int $room_id, array $data): bool;

    /**
     * @use 创建抽奖
     * @param array $raffles
     * @return array
     */
    abstract protected static function createLottery(array $raffles): array;

    /**
     * @use 解析抽奖返回
     * @param array $results
     * @return mixed
     */
    abstract protected static function parseLottery(array $results): mixed;

    /**
     * @use 二维数组按key排序
     * @param $arr
     * @param $key
     * @param string $type
     * @return array
     */
    protected static function arrKeySort($arr, $key, string $type = 'asc'): array
    {
        switch ($type) {
            case 'desc':
                array_multisort(array_column($arr, $key), SORT_DESC, $arr);
                return $arr;
            case 'asc':
                array_multisort(array_column($arr, $key), SORT_ASC, $arr);
                return $arr;
            default:
                return $arr;
        }
    }

    /**
     * @use 去重检测
     * @param $lid
     * @param bool $filter
     * @return bool
     */
    protected static function toRepeatLid($lid, bool $filter = true): bool
    {
        $lid = (int)$lid;
        if (in_array($lid, static::$all_list)) {
            return true;
        }
        if (count(static::$all_list) > 4000) {
            static::$all_list = array_values(array_splice(static::$all_list, 2000, 2000));
        }
        if ($filter) {
            static::$all_list[] = $lid;
        }
        return false;
    }

    /**
     * @use 数据推入队列
     * @param array $data
     * @return bool
     */
    public static function pushToQueue(array $data): bool
    {
        // 开关
        if (!getEnable(static::ACTIVE_SWITCH)) {
            return false;
        }
        // 黑屋
        if (static::getPauseStatus()) {
            return false;
        }
        $current_rid = (int)$data['rid'];
        // 去重
        if (static::toRepeatLid($current_rid, false)) {
            return false;
        }
        // 拒绝钓鱼 防止重复请求
        if (in_array($current_rid, static::$banned_rids)) {
            return false;
        }
        $banned_status = Live::fishingDetection($current_rid);
        if ($banned_status) {
            static::$banned_rids[] = $current_rid;
            return false;
        }
        // 实际检测
        $raffles_info = static::check($current_rid);
        if (!empty($raffles_info)) {
            static::parseLotteryInfo($current_rid, $raffles_info);
        }
        $wait_num = count(static::$wait_list);
        if ($wait_num > 10 && ($wait_num % 2)) {
            Log::info("当前队列中共有 $wait_num 个" . static::ACTIVE_TITLE . "待抽奖");
        }
        return true;
    }
}
