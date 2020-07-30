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
    // 过滤类型
    private static $filter_type = [];
    // 默认关注 特殊关注  等待关注
    private static $default_follows = [];
    private static $special_follows = [];
    public static $wait_un_follows = [];
    // 特殊分组  分组ID
    private static $group_name = "玄不改非"; // 氪不改命
    private static $group_id = null;


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
            self::$group_id = $tag_id ? $tag_id : User::createRelationTag(self::$group_name);
        }
        // 获取需要关注的
        $data = Live::getRoomInfo($room_id);
        if ($data['code'] == 0 && isset($data['data'])) {
            $need_follow_uid = $data['data']['uid'];
        } else {
            return;
        }
        // 是否在关注里
        $default_follows = self::getDefaultFollows();
        if (!in_array($need_follow_uid, $default_follows)) {
            User::setUserFollow($need_follow_uid);
            User::tagAddUsers($need_follow_uid, self::$group_id);
            // 添加到检测中奖
            array_push(self::$wait_un_follows, [
                'uid' => $need_follow_uid,
                'anchor_id' => $anchor_id,
                'time' => $time,
            ]);
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
            array_push($new_list, $wait_un_follow);
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
            array_push(self::$default_follows, 1);
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
        $default_words = [
            '拉黑', '黑名单', '脸皮厚', '没有奖品', '无奖', '脸皮厚', 'ceshi', '测试', '测试', '测试', '脚本',
            '抽奖号', '星段位', '星段位', '圣晶石', '圣晶石', '水晶', '水晶', '万兴神剪手', '万兴神剪手',
            '自付邮费', '自付邮费', 'test', 'Test', 'TEST', '加密', 'QQ', '测试', '測試', 'VX', 'vx',
            'ce', 'shi', '这是一个', 'lalall', '第一波', '第二波', '第三波', '测试用', '抽奖标题', '策是',
            '房间抽奖', 'CESHI', 'ceshi', '奖品A', '奖品B', '奖品C', '硬币', '无奖品', '白名单', '我是抽奖',
            '0.1', '五毛二', '一分', '一毛', '0.52', '0.66', '0.01', '0.77', '0.16', '照片', '穷', '0.5',
            '0.88', '双排', '1毛', '1分', '1角', 'P口罩', '素颜', '写真', '图包', '五毛', '一角', '冥币',
            '自拍', '日历', '0.22', '加速器', '越南盾','毛','分','限','0.','角','〇点','①元',
            '一起玩','不包邮','邮费','续期卡','儿时','闪宠','大师球','一元','两元','两块','赛车',
            '代币','一块','一局','好友位','通话','首胜','代金券','辣条','补贴','抵用券','主播素颜照',
            '武器箱棺材板','游戏道具','优惠券','日元','发音课','壹元','零点','舰长五折券','上车',
            '没有钱','女装','肥宅快乐水','哥斯拉','公主连结','pokemmo','宝可>梦','明日方舟','雪碧','公主连接',
            '专属头衔','FF14','韩元','空洞骑士','老婆饼','稀世时装','洛克衣服','帮过图','证件照','自抽号',
            '晶耀之星','伊洛纳','〇.','②元','③元','0·','繁华美化','喵喵喵','闪伊布','①圆','o点','金达摩','嗷呜',
            '游戏位','S-追光者','OWL','勾玉','跟yo宝游戏','三元','怡宝','蛋闪迷>你冰','哥伦比亚比索','油条'
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
        // 分组操作
        if (getenv('ANCHOR_UNFOLLOW') == 'true' && $de_raw['require_text'] == '关注主播') {
            self::addToGroup($room_id, $de_raw['id'], time() + $de_raw['time'] + 5);
        }
        // 推入列表
        $data = [
            'room_id' => $room_id,
            'raffle_id' => $de_raw['id'],
            'raffle_name' => $de_raw['award_name'],
            'wait' => time() + mt_rand(5, 25)
        ];
        Statistics::addPushList($data['raffle_name']);
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
                    'raffle_id' => $raffle['raffle_id'],
                    'raffle_name' => $raffle['raffle_name']
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
            // {"code":-403,"data":null,"message":"访问被拒绝","msg":"访问被拒绝"}
            if (isset($de_raw['code']) && $de_raw['code'] == 0) {
                Statistics::addSuccessList($data['raffle_name']);
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": 参与抽奖成功~");
            } elseif (isset($de_raw['msg']) && $de_raw['code'] == -403 && $de_raw['msg'] == '访问被拒绝') {
                Log::debug("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['message']}");
                self::pauseLock();
            } else {
                Log::notice("房间 {$data['room_id']} 编号 {$data['raffle_id']} " . self::ACTIVE_TITLE . ": {$de_raw['message']}");
            }
        }
    }
}
