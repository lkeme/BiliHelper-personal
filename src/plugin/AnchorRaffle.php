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
use BiliHelper\Util\BaseRaffle;

class AnchorRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '天选之子';
    const ACTIVE_SWITCH = 'live_anchor';

    protected static array $wait_list = [];
    protected static array $finish_list = [];
    protected static array $all_list = [];
    // 过滤类型
    private static array $filter_type = [];
    // 默认关注 特殊关注  等待关注
    private static array $default_follows = [];
    private static array $special_follows = [];
    public static array $wait_un_follows = [];
    // 特殊分组  分组ID
    private static string $group_name = "玄不改非"; // 氪不改命
    private static int|null $group_id = null;


    /**
     * @use 删除分组
     * @param int $un_follow_uid
     * @param int $anchor_id
     * @param bool $un_follow
     */
    public static function delToGroup(int $un_follow_uid, int $anchor_id, bool $un_follow = true)
    {
        // 取关
        if ($un_follow) {
            User::setUserFollow($un_follow_uid, true);
        }
        self::delValue($un_follow_uid, $anchor_id);
    }

    /**
     * @use 添加分组
     * @param int $room_id
     * @param int $anchor_id
     * @param int $time
     */
    private static function addToGroup(int $room_id, int $anchor_id, int $time)
    {
        // 获取分组id
        if (is_null(self::$group_id)) {
            $tags = User::fetchTags();
            $tag_id = array_search(self::$group_name, $tags);
            // 如果不存在则调用创建
            self::$group_id = $tag_id ?: User::createRelationTag(self::$group_name);
        }
        // 获取需要关注的
        $data = Live::getRealRoomID($room_id, true);
        if ($data['uid']) {
            $need_follow_uid = $data['uid'];
        } else {
            return;
        }
        // 是否在关注里
        $default_follows = self::getDefaultFollows();
        if (!in_array($need_follow_uid, $default_follows)) {
            User::setUserFollow($need_follow_uid);
            User::tagAddUsers($need_follow_uid, self::$group_id);
            // 添加到检测中奖
            self::$wait_un_follows[] = [
                'uid' => $need_follow_uid,
                'anchor_id' => $anchor_id,
                'time' => $time,
            ];
        }
    }

    /**
     * @use 删除值并重置数组
     * @param int $uid
     * @param int $anchor_id
     */
    private static function delValue(int $uid, int $anchor_id)
    {
        $new_list = [];
        foreach (self::$wait_un_follows as $wait_un_follow) {
            if ($wait_un_follow['uid'] == $uid && $wait_un_follow['uid'] == $anchor_id) {
                continue;
            }
            $new_list[] = $wait_un_follow;
        }
        self::$wait_un_follows = $new_list;
    }

    /**
     * @use 获取默认关注
     * @return array
     */
    private static function getDefaultFollows(): array
    {
        if (!empty(self::$default_follows)) {
            return self::$default_follows;
        }
        // 如果获取默认关注错误 或者 为空则补全一个
        self::$default_follows = User::fetchTagFollowings();
        if (empty(self::$default_follows)) {
            self::$default_follows[] = 1;
        }
        return self::$default_follows;
    }

    /**
     * @use 过滤奖品
     * @param string $prize_name
     * @return bool
     */
    protected static function filterPrizeWords(string $prize_name): bool
    {
        $default_words = self::$store->get("Anchor.default");
        $custom_words = empty($words = getConf('filter_words', 'live_anchor')) ? [] : explode(',', $words);
        $total_words = array_merge($default_words, $custom_words);
        foreach ($total_words as $word) {
            if (str_contains($prize_name, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @use 解析数据
     * @param int $room_id
     * @param array $data
     * @return bool
     */
    protected static function parseLotteryInfo(int $room_id, array $data): bool
    {
        // 防止异常
        if (!array_key_exists('anchor', $data['data'])) {
            return false;
        }
        $de_raw = $data['data']['anchor'];
        if (empty($de_raw)) {
            return false;
        }
        // 无效抽奖
        if ($de_raw['join_type'] || $de_raw['lot_status']) {
            return false;
        }
        // 过滤抽奖范围
        self::$filter_type = empty(self::$filter_type) ? explode(',', getConf('limit_type', 'live_anchor')) : self::$filter_type;
        if (!in_array((string)$de_raw['require_type'], self::$filter_type)) {
            return false;
        }
        // 过滤奖品关键词
        if (self::filterPrizeWords($de_raw['award_name'])) {
            return false;
        }
        // 去重
        if (self::toRepeatLid($de_raw['id'])) {
            return false;
        }
        // 分组操作
        if (getConf('auto_unfollow', 'live_anchor') && $de_raw['require_text'] == '关注主播') {
            self::addToGroup($room_id, $de_raw['id'], time() + $de_raw['time'] + 5);
        }
        // 推入列表
        $data = [
            'room_id' => $room_id,
            'raffle_id' => $de_raw['id'],
            'raffle_name' => $de_raw['award_name'],
            'remarks' => self::ACTIVE_TITLE,
            'wait' => time() + mt_rand(5, 25)
        ];
//        Statistics::addPushList($data['raffle_name']);
        Statistics::addPushList(self::ACTIVE_TITLE);
        self::$wait_list[] = $data;
        return true;
    }

    /**
     * @use 创建抽奖任务
     * @param array $raffles
     * @return array
     */
    protected static function createLottery(array $raffles): array
    {
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/Anchor/Join';
        $tasks = [];
        foreach ($raffles as $raffle) {
            $payload = [
                'id' => $raffle['raffle_id'],
                'roomid' => $raffle['room_id'],
                'platform' => 'pc',
                'csrf_token' => getCsrf(),
                'csrf' => getCsrf(),
                'visit_id' => ''
            ];
            $tasks[] = [
                'payload' => Sign::common($payload),
                'source' => [
                    'room_id' => $raffle['room_id'],
                    'raffle_id' => $raffle['raffle_id'],
                    'raffle_name' => $raffle['raffle_name']
                ]
            ];
        }
        // print_r($results);
        return Curl::async('app', $url, $tasks);
    }

    /**
     * @use 解析抽奖信息
     * @param array $results
     * @return string
     */
    protected static function parseLottery(array $results): string
    {
        foreach ($results as $result) {
            $data = $result['source'];
            $content = $result['content'];
            $de_raw = json_decode($content, true);
            // {"code":-403,"data":null,"message":"访问被拒绝","msg":"访问被拒绝"}
            if (isset($de_raw['code']) && $de_raw['code'] == 0) {
                Statistics::addSuccessList(self::ACTIVE_TITLE);
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . "-(" . $data['raffle_name'] . "): 参与抽奖成功~");
            } elseif (isset($de_raw['msg']) && $de_raw['code'] == -403 && $de_raw['msg'] == '访问被拒绝') {
                Log::debug("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . "-(" . $data['raffle_name'] . "): {$de_raw['message']}");
                self::pauseLock();
            } else {
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . "-(" . $data['raffle_name'] . "): {$de_raw['message']}");
            }
        }
        return '';
    }
}
