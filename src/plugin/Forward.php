<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

/**
 * @Blog   http://blog.jianxiaodai.com
 * @author  菜如狗怎么了
 * @date 2020-12
 *
 * 2021-3-14 FEAT:增加自动回复语言更改
 * @author:zymooll
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Util\TimeLock;
use function JBZoo\Data\json;

class Forward
{
    use TimeLock;

    public static array $default_follows = [];

    public static array $un_follows = [];
    public static array $del_dynamic = [];

    public static array $already = [];

    private static string $group_name = "氪可改命";

    private static int|null $group_id = null;

    private static array $draw_follow = [];

    private static string $repository = APP_DATA_PATH . 'reply_words.json';

    /**
     * @use run
     */
    public static function run(): void
    {

        if (self::getLock() > time()) {
            return;
        }
        self::setPauseStatus();
        if (!self::start()) {
            self::setLock(60 * 60);
            return;
        }
        self::setLock(5 * 60);
    }


    public static function start(): bool
    {
        // 取关未中奖
        if (getConf('clear_group_follow', 'dynamic')) {
            self::clearDynamic();
        }
        // 自动转发关注评论
        if (getConf('enable', 'dynamic')) {
            self::autoRepost();
        }
        // 强制清除抽奖关注组
        if (getConf('clear_group_follow', 'dynamic')) {
            self::clearAllDynamic();
            self::clearFollowGroup();
        }
        return true;
    }

    /**
     * 自动转发抽奖
     */
    public static function autoRepost(): void
    {
        $article_list = Dynamic::getAwardTopic();
        foreach ($article_list as $did => $article) {

            if (isset(self::$already[$did])) {
                //重复
                Log::info("[动态抽奖]-已转发 跳过: $did {$article['uid']}");
                continue;
            }
            // 评论
            Log::info("[动态抽奖]-评论: $did {$article['rid']}");
            if (Dynamic::dynamicReplyAdd($article['rid'], self::getReplyMsg())) {
                // 转发
                Log::info("[动态抽奖]-转发: $did");
                if (Dynamic::dynamicRepost($did, self::getReplyMsg())) {
                    // 关注
                    Log::info("[动态抽奖]-关注: $did {$article['uid']}");
                    self::addToGroup($article['uid']); //
                    self::$already[$did] = 1;
                }
            }
            sleep(1);
        }
    }

    /**
     * 清理无效的动态
     */
    private static function clearDynamic(): void
    {
        $dynamicList = Dynamic::getMyDynamic();

        Log::info("[动态抽奖]-检测中奖 动态数: " . count($dynamicList));
        foreach ($dynamicList as $dynamic) {
            $flag = false;
            $msg = '';
            $did = $dynamic['desc']['dynamic_id'];
            $card = json_decode($dynamic['card'], true);


            if (isset($card["item"]["miss"]) && $card["item"]["miss"] == 1) {
                $flag = true;
                $msg = "[动态抽奖]-删除动态 源动态已删除  $did";
            }
            if (isset($card["origin_extension"]['lott'])) {
                $lott = json_decode($card["origin_extension"]["lott"], true);
                if (isset($lott["lottery_time"]) && $lott["lottery_time"] <= time()) {
                    $flag = true;
                    $msg = "[动态抽奖]-删除动态 抽奖已过期 $did";
                }
            }
            if (isset($card["item"]["orig_dy_id"])) {
                $ret = Dynamic::getLotteryNotice($card["item"]["orig_dy_id"]);
                if (isset($ret['data']['lottery_time']) && $ret['data']['lottery_time'] <= time()) {
                    $flag = true;
                    $msg = "[动态抽奖]-删除动态 抽奖已过期 $did";
                }
            }

//            if (isset($card['origin'])) {
//                $origin = json_decode($card["origin"], true);
//                if (isset($origin['item']['description'])) {
//                    if (isset($card["item"]['description'])) {
//                        $text = $origin["item"]["description"];
//                    } elseif (isset($card["item"]['content'])) {
//                        $text = $card["item"]["content"];
//                    } else {
//                        $text = null;
//                    }
//                    if ($text) {
//                        continue;
//                        // 关键字过滤
//                    }
//                }
//            }


            if ($flag) {
                self::$del_dynamic[$did] = $msg;
                if (isset($card['origin_user']['info']['uid'])) {
                    self::$un_follows[] = $card['origin_user']['info']['uid'];
                }
            }
        }

        // 取关
        foreach (self::$del_dynamic as $did => $msg) {
            Dynamic::removeDynamic($did);
            Log::info($msg);
            unset(self::$del_dynamic[$did]);
        }
        // 取关
        foreach (self::$un_follows as $uid) {
            // 非转发抽奖动态关注的up 不取关
            if (isset(self::$draw_follow[$uid])) {
                Log::info("[动态抽奖]-未中奖-取关 $uid");
                User::setUserFollow($uid, true);
            }
        }
    }

    /**
     * @use 取关
     */
    private static function clearFollowGroup(): void
    {
        $tags = User::fetchTags();
        foreach ($tags as $gid => $name) {
            if (!in_array($name, ['玄不改非', '氪可改命'])) {
                continue;
            }
            $r = User::fetchTagFollowings($gid);
            foreach ($r as $uid) {
                Log::info("[清除抽奖组关注]  : $uid");
                User::setUserFollow($uid, true);
            }
        }

    }

    /**
     * @use 清理动态
     */
    private static function clearAllDynamic(): void
    {
        $dynamicList = Dynamic::getMyDynamic();
        $msg_list = self::getReplyMsgList();
        foreach ($dynamicList as $dynamic) {
            $did = $dynamic['desc']['dynamic_id'];
            $card = json_decode($dynamic['card'], true);
            foreach ($msg_list as $msg) {
                // 异常情况 跳过
                if (!isset($card['item']['content']) || !$msg) {
                    continue;
                }
                if (str_contains($card['item']['content'], $msg)) {
                    Log::info("[删除所有动态] 删除动态 $did");
                    Dynamic::removeDynamic($did);
                }
            }
        }
    }

    /**
     * @use 添加分组
     * @param int $need_follow_uid
     * @param int $anchor_id
     * @param int $time
     */
    private static function addToGroup(int $need_follow_uid, int $anchor_id = 0, int $time = 0): void
    {
        // 获取分组id
        if (is_null(self::$group_id)) {
            $tags = User::fetchTags();
            $tag_id = array_search(self::$group_name, $tags);
            // 如果不存在则调用创建
            self::$group_id = $tag_id ?: User::createRelationTag(self::$group_name);
        }
        // 是否在关注里
        $default_follows = self::getDefaultFollows();
        if (!in_array($need_follow_uid, $default_follows)) {
            User::setUserFollow($need_follow_uid); // 关注
            User::tagAddUsers($need_follow_uid, self::$group_id); // 转到分组中
            self::$draw_follow[$need_follow_uid] = 1; // 记录转发抽奖关注的up
        }
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
     * @use 获取回复 all
     * @return array
     */
    private static function getReplyMsgList(): array
    {
        $data = json(self::$repository);
        // 给个默认值
        $data = $data->find("DynamicForward.default", []);
        $data[] = getConf('auto_reply_text', 'dynamic');
        return $data;
    }

    /**
     * @use 获取回复 1
     * @return string
     */
    private static function getReplyMsg(): string
    {
        //更改自动回复
        if (getConf('auto_reply_text', 'dynamic') != '') {
            $msg = getConf('auto_reply_text', 'dynamic');
        } else {
            $data = self::getReplyMsgList();
            shuffle($data);
            $msg = array_pop($data);
        }
        Log::info("已将自动回复改为\"$msg\"");
        return $msg;
    }

}
