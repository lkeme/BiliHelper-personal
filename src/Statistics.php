<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019
 */

namespace lkeme\BiliHelper;


class Statistics
{
    public static $lock = 0;

    // 推送 参加 成功
    private static $push_list = [];
    private static $join_list = [];
    private static $success_list = [];


    // 日志
    public static function run()
    {
        if (self::$lock > time()) {
            return;
        }
        self::getStormResult();

        self::$lock = time() + 5 * 60;
    }


    // 添加推送
    public static function addPushList(string $key): bool
    {
        // 初始化三个必要值
        if (!array_key_exists($key, self::$push_list)) {
            self::$push_list[$key] = [];
            self::$join_list[$key] = [];
            self::$success_list[$key] = [];
        }

        array_push(self::$push_list[$key], 1);
        return true;
    }

    // 添加参与
    public static function addJoinList(string $key): bool
    {
        array_push(self::$join_list[$key], 1);
        return true;
    }

    // 添加成功
    public static function addSuccessList(string $key): bool
    {
        array_push(self::$success_list[$key], 1);
        return true;
    }

    // 获取结果
    private static function getStormResult(): bool
    {
        if (empty(self::$push_list)) {
            return false;
        } else {
            Log::info("-----------密----------封---------线------------");
        }
        foreach (self::$push_list as $key => $val) {
            $title = $key;
            $push_num = count(self::$push_list[$key]);
            $join_num = count(self::$join_list[$key]);
            $success_num = count(self::$success_list[$key]);
            $content = "{$title}: #推送 {$push_num} #参与 {$join_num} #成功 {$success_num}";
            Log::notice($content);
        }
        Log::info("-----------密----------封---------线------------");
        return true;
    }
}