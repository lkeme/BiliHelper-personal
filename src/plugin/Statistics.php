<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Util\TimeLock;


class Statistics
{
    use TimeLock;

    private static $push_list = [];
    private static $join_list = [];
    private static $success_list = [];


    public static function run()
    {
        if (self::getLock() > time()) {
            return;
        }
        self::outputResult();

        self::setLock(5 * 60);
    }


    /**
     * @use 添加推送
     * @param string $key
     * @return bool
     */
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


    /**
     * @use 添加参与
     * @param string $key
     * @return bool
     */
    public static function addJoinList(string $key): bool
    {
        array_push(self::$join_list[$key], 1);
        return true;
    }


    /**
     * @use 添加成功
     * @param string $key
     * @return bool
     */
    public static function addSuccessList(string $key): bool
    {
        array_push(self::$success_list[$key], 1);
        return true;
    }


    /**
     * @use 输出结果
     * @return bool
     */
    private static function outputResult(): bool
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