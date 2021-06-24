<?php

/**
 * @blog    http://blog.jianxiaodai.com
 * @author  菜如狗怎么了
 * @date    2020-12
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Curl;
use BiliHelper\Util\FilterWords;

class Dynamic
{
    use FilterWords;

    //  228584  14027    434405   7019788   3230836
    private static $topic_list = [
        3230836 => '',
        434405 => '',
        7019788 => ''
    ];


    private static $article_list = [];

    /**
     * 获取抽奖话题下的帖子
     */
    public static function getAwardTopic(): array
    {

        foreach (self::$topic_list as $t_id => $t_name) {
            $url = 'https://api.vc.bilibili.com/topic_svr/v1/topic_svr/topic_new?topic_id=' . $t_id;
            $data = Curl::request('get', $url);
            $data = json_decode($data, true);

            // new
            foreach ($data['data']['cards'] as $article) {
                $article_id = $article['desc']['dynamic_id'];
                // 获取 description
                $card = json_decode($article['card'], true);
                $item = [
                    'uid' => $article['desc']['uid'],
                    'rid' => $article['desc']['rid'],
                    'did' => $article_id,
                    'tm' => $article['desc']['timestamp'],
                    'desc' => $card['item']['description']
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
    public static function getDynamicDetail($did)
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
    public static function getLotteryNotice($did)
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
     * @return array|mixed
     */
    public static function getDynamicTab(int $uid = 0, int $type_list = 268435455)
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
        // 过滤描述
        $default_words = self::$store->get("DynamicForward.default");
        $custom_words = empty($words = getConf('filter_words', 'dynamic')) ? [] : explode(',', $words);
        $total_words = array_merge($default_words, $custom_words);
        foreach ($total_words as $word) {
            if (strpos($item['desc'], $word) !== false) {
                return true;
            }
        }
        // 过滤UID
        $uid_list = self::$store->get("Common.uid_list");
        if (array_key_exists((int)$item['uid'], $uid_list)) {
            return true;
        }
        // 过滤粉丝数量
        if (Live::getMidFollower((int)$item['uid']) < getConf('min_fans_num', 'dynamic')) {
            return true;
        }
        return false;
    }
}