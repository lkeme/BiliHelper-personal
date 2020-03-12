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

class AnchorRaffle extends BaseRaffle
{
    const ACTIVE_TITLE = '天选时刻';
    const ACTIVE_SWITCH = 'USE_ANCHOR';

    use TimeLock;

    protected static $wait_list = [];
    protected static $finish_list = [];
    protected static $all_list = [];

    private static $filter_type = [];


    /**
     * @use 过滤奖品
     * @param string $prize_name
     * @return bool
     */
    protected static function filterPrizeWords(string $prize_name): bool
    {
        $default_words = [
            '拉黑', '黑名单', '脸皮厚', '没有奖品', '无奖', '脸皮厚', 'ceshi', '测试', '测试', '测试', '脚本',
            '抽奖号', '星段位', '星段位', '圣晶石', '圣晶石', '水晶', '水晶', '万兴神剪手', '万兴神剪手',
            '自付邮费', '自付邮费', 'test', 'Test', 'TEST', '加密', 'QQ', '测试', '測試', 'VX', 'vx',
            'ce', 'shi', '这是一个', 'lalall', '第一波', '第二波', '第三波', '测试用', '抽奖标题', '策是',
            '房间抽奖', 'CESHI', 'ceshi', '奖品A', '奖品B', '奖品C', '硬币', '无奖品', '白名单', '我是抽奖',
            '0.1', '五毛二', '一分', '一毛', '0.52', '0.66', '0.01', '0.77', '0.16', '照片', '穷', '0.5',
            '0.88', '双排', '1毛', '1分', '1角', 'P口罩', '素颜', '写真', '图包', '五毛', '一角', '冥币',
            '自拍', '日历', '0.22', '加速器', '越南盾'
        ];
        $custom_words = empty(getenv('ANCHOR_FILTER_WORDS')) ? [] : explode(',', getenv('ANCHOR_FILTER_WORDS'));
        $total_words = array_merge($default_words, $custom_words);
        foreach ($total_words as $word) {
            if (strpos($prize_name, $word) !== false) {
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
        self::$filter_type = empty(self::$filter_type) ? explode(',', getenv('ANCHOR_TYPE')) : self::$filter_type;
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
        // 推入列表
        $data = [
            'room_id' => $room_id,
            'raffle_id' => $de_raw['id'],
            'raffle_name' => $de_raw['award_name'],
            'wait' => time() + random_int(5, 25)
        ];
        Statistics::addPushList(self::ACTIVE_TITLE);
        array_push(self::$wait_list, $data);
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
        $results = [];
        $user_info = User::parseCookies();
        foreach ($raffles as $raffle) {
            $payload = [
                'id' => $raffle['raffle_id'],
                'roomid' => $raffle['room_id'],
                'platform' => 'pc',
                'csrf_token' => $user_info['token'],
                'csrf' => $user_info['token'],
                'visit_id' => ''
            ];
            array_push($tasks, [
                'payload' => Sign::common($payload),
                'source' => [
                    'room_id' => $raffle['room_id'],
                    'raffle_id' => $raffle['raffle_id']
                ]
            ]);
        }
        $results = Curl::async('app', $url, $tasks);
        # print_r($results);
        return $results;
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
            $de_raw = json_decode($content, true);
            if (isset($de_raw['code']) && $de_raw['code'] == 0) {
                Statistics::addSuccessList(self::ACTIVE_TITLE);
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": 参与抽奖成功~");
            } else {
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['message']}");
            }
        }
    }
}
