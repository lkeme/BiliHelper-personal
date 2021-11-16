<?php

/**
 * @blog    http://blog.jianxiaodai.com
 * @author  菜如狗怎么了
 * @date    2020-12
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Curl;
use BiliHelper\Core\Log;
use BiliHelper\Util\FilterWords;

class Dynamic
{
    use FilterWords;

    // Todo 活动订阅
    // https://www.bilibili.com/blackboard/activity-WeqT10t1ep.html
    // https://api.vc.bilibili.com/topic_svr/v1/topic_svr/fetch_dynamics?topic_name=%E4%BA%92%E5%8A%A8%E6%8A%BD%E5%A5%96&sortby=2
    // https://t.bilibili.com/topic/name/%E5%8A%A8%E6%80%81%E6%8A%BD%E5%A5%96/feed

    private static array $topic_list = [
        '互动抽奖' => '3230836',
        '转发抽奖' => '434405',
        '动态抽奖' => '7146512',
        '关注抽奖' => '5608480',
        // '关注+转发' => '7544627',
        // '评论抽奖'=>'2630459',
        // '转发关注评论抽奖'=>'8339319',
        // '转发+评论抽奖'=> '7169938',
        // '关注评论抽奖'=>'8078587',
        // '转发评论抽奖' => '7019788',
        // '抽奖'=>'228584',

    ];


    private static array $article_list = [];

    /**
     * 获取抽奖话题下的帖子
     */
    public static function getAwardTopic(): array
    {
        foreach (self::$topic_list as $t_name => $t_id) {
            Log::info("获取关键字 $t_name - $t_id");
            $url = 'https://api.vc.bilibili.com/topic_svr/v1/topic_svr/topic_new?topic_id=' . $t_id;
            $data = Curl::request('get', $url);
            // 失败跳过
            if (is_null($data)) continue;
            $data = json_decode($data, true);
            // new
            foreach ($data['data']['cards'] as $article) {
                $article_id = $article['desc']['dynamic_id'];
                // 获取 description
                $card = json_decode($article['card'], true);

                if (isset($card['category']) && isset($card['categories'])) {
                    // 处理专栏 提前处理
                    continue;
                } elseif (isset($card['aid']) && isset($card['cid'])) {
                    //  处理视频转发
                    $description = $card['dynamic'];
                } elseif (array_key_exists("description", $card['item'])) {
                    // 主动态
                    $description = $card['item']['description'];
                } elseif (array_key_exists("content", $card['item'])) {
                    // 子动态
                    // Todo 暂时跳过 需要合适的处理方法
                    // description = $card['item']['content'];
                    continue;
                } else {
                    // 链接到视频的动态 少数 跳过
                    // print_r($card);
                    continue;
                }
                $item = [
                    'uid' => $article['desc']['uid'],
                    'rid' => $article['desc']['rid'],
                    'did' => $article_id,
                    'tm' => $article['desc']['timestamp'],
                    'desc' => $description
                ];
                // 过滤为true 就跳过
                if (self::filterLayer($item)) continue;
                // 不要原始desc
                unset($item['desc']);
                self::$article_list[$article_id] = $item;
            }
            // $has_more = 0;
            // more ??
            // https://api.vc.bilibili.com/topic_svr/v1/topic_svr/topic_history?topic_name=转发抽奖&offset_dynamic_id=454347930068783808
        }
        $num = count(self::$article_list);
        Log::info("获取到 $num 条有效动态");
        return self::$article_list;
    }

    /**
     * 动态转发
     * @param $rid
     * @param string $content
     * @param int $type
     * @param int $repost_code
     * @param string $from
     * @param string $extension
     * @return bool
     */
    public static function dynamicRepost($rid, string $content = "", int $type = 1, int $repost_code = 3000, string $from = "create.comment", string $extension = '{"emoji_type":1}'): bool
    {
        $url = "https://api.vc.bilibili.com/dynamic_repost/v1/dynamic_repost/reply";
        $payload = [
            "uid" => getUid(),
            "rid" => $rid,
            "type" => $type,
            "content" => $content,
            "extension" => $extension,
            "repost_code" => $repost_code,
            "from" => $from,
        ];
        $raw = Curl::post('app', $url, $payload);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0 && isset($de_raw['data'])) {
            return true;
        }
        return false;
    }

    /**
     * 发表评论
     * @param int $rid
     * @param string $message
     * @param int $type
     * @param int $plat
     * @return bool
     */
    public static function dynamicReplyAdd(int $rid, string $message = "", int $type = 11, int $plat = 1): bool
    {
        $url = "https://api.bilibili.com/x/v2/reply/add";
        $payload = [
            "oid" => $rid,
            "plat" => $plat,
            "type" => $type,
            "message" => $message,
            "csrf" => getCsrf(),
        ];
        $raw = Curl::post('app', $url, $payload);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0 && isset($de_raw['data'])) {
            return true;
        }
        return false;
    }

    /**
     * 删除指定动态
     * @param $did
     * @return bool
     */
    public static function removeDynamic($did): bool
    {
        $url = 'https://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/rm_dynamic';
        $payload = [
            "dynamic_id" => $did,
            "csrf_token" => getCsrf(),
        ];
        $raw = Curl::post('app', $url, $payload);
        $de_raw = json_decode($raw, true);
        if ($de_raw['code'] == 0 && isset($de_raw['data'])) {
            return true;
        }
        return false;
    }

    /**
     * 获取个人发布的动态
     * @param int $uid
     * @return array
     */
    public static function getMyDynamic(int $uid = 0): array
    {
        $uid = $uid == 0 ? getUid() : $uid;
        $url = "https://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/space_history";
        $offset = '';
        $has_more = true;
        $card_list = [];
        while ($has_more) {
            $payload = [
                "host_uid" => $uid,
                "need_top" => 1,
                "offset_dynamic_id" => $offset,
            ];
            $raw = Curl::get('app', $url, $payload);
            $de_raw = json_decode($raw, true);
            $has_more = $de_raw['data']['has_more'] == 1;
            if (!isset($de_raw['data']['cards'])) {
                continue;
            }
            $card_list = array_merge($card_list, $de_raw['data']['cards']);
            foreach ($de_raw['data']['cards'] as $card) {
                $offset = $card['desc']['dynamic_id_str'];
            }
        }
        return $card_list;
    }

    /**
     * 获取动态详情
     * @param $did
     * @return mixed
     */
    public static function getDynamicDetail($did): mixed
    {
        $url = "https://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/get_dynamic_detail";
        $payload = [
            "dynamic_id" => $did,
        ];
        $raw = Curl::get('app', $url, $payload);
        return json_decode($raw, true);

    }

    /**
     * 获取抽奖动态信息
     * @param $did
     * @return mixed
     */
    public static function getLotteryNotice($did): mixed
    {
        $url = 'https://api.vc.bilibili.com/lottery_svr/v1/lottery_svr/lottery_notice';
        $payload = [
            "dynamic_id" => $did,
        ];
        $raw = Curl::get('app', $url, $payload);
        return json_decode($raw, true);
    }

    /**
     * 获取个人动态TAB列表
     * @param int $uid
     * @param int $type_list
     * @return mixed
     */
    public static function getDynamicTab(int $uid = 0, int $type_list = 268435455): mixed
    {
        $uid = $uid == 0 ? getUid() : $uid;
        $url = "https://api.vc.bilibili.com/dynamic_svr/v1/dynamic_svr/dynamic_new";
        $offset = '';
        $has_more = true;
        $card_list = [];
        while ($has_more) {
            $payload = [
                "uid" => $uid,
                "type_list" => $type_list,
                "offset_dynamic_id" => $offset,
            ];
            $raw = Curl::get('app', $url, $payload);
            $de_raw = json_decode($raw, true);
            if (!isset($de_raw['data']['cards'])) {
                continue;
            }
            $card_list = $de_raw['data']['cards'];
            $has_more = $de_raw['data']['has_more'] == 1;
            foreach ($de_raw['data']['cards'] as $card) {
                $offset = $card['desc']['dynamic_id_str'];
            }
        }
        return $card_list;
    }

    /**
     * @use 过滤层
     * @param array $item
     * @return bool
     */
    protected static function filterLayer(array $item): bool
    {
        self::loadJsonData();
        // 过滤描述
        $default_words = self::$store->get("DynamicForward.default");
        $common_words = self::$store->get("Common.default");
        $custom_words = empty($words = getConf('filter_words', 'dynamic')) ? [] : explode(',', $words);
        $total_words = array_merge($default_words, $custom_words, $common_words);
        foreach ($total_words as $word) {
            if (str_contains($item['desc'], $word)) {
                Log::warning("当前动态#{$item['did']}触发关键字过滤 $word");
                return true;
            }
        }
        // 过滤UID
        $uid_list = self::$store->get("Common.uid_list");
        if (array_key_exists((int)$item['uid'], $uid_list)) {
            Log::warning("当前动态#{$item['did']}触发UP黑名单过滤 {$item['uid']}");
            return true;
        }
        // 过滤粉丝数量
        if (($num = Live::getMidFollower((int)$item['uid'])) < getConf('min_fans_num', 'dynamic')) {
            Log::warning("当前动态#{$item['did']}触发UP粉丝数量过滤 $num");
            return true;
        }
        return false;
    }
}
