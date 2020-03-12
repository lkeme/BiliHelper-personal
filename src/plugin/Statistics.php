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
        self::initKeyValue(self::$push_list, $key);
        self::$push_list[$key]++;
        return true;
    }


    /**
     * @use 添加参与
     * @param string $key
     * @return bool
     */
    public static function addJoinList(string $key): bool
    {
        self::initKeyValue(self::$join_list, $key);
        self::$join_list[$key]++;
        return true;
    }


    /**
     * @use 添加成功
     * @param string $key
     * @return bool
     */
    public static function addSuccessList(string $key): bool
    {
        self::initKeyValue(self::$success_list, $key);
        self::$success_list[$key]++;
        return true;
    }

    /**
     * @use 初始化键值
     * @param array $target
     * @param string $key
     * @param int $value
     * @return bool
     */
    private static function initKeyValue(array &$target, string $key, $value = 0): bool
    {
        if (!array_key_exists($key, $target)) {
            $target[$key] = $value;
        }
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
        }
        Log::info("-----------密----------封---------线------------");
        foreach (self::$push_list as $key => $val) {
            $title = $key;
            $push_num = isset(self::$push_list[$key]) ? self::$push_list[$key] : 0;
            $join_num = isset(self::$join_list[$key]) ? self::$join_list[$key] : 0;
            $success_num = isset(self::$success_list[$key]) ? self::$success_list[$key] : 0;
            $content = "{$title}: #推送 {$push_num} #参与 {$join_num} #成功 {$success_num}";
            Log::notice($content);
        }
        Log::info("-----------密----------封---------线------------");
        return true;
    }
}