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
use BiliHelper\Util\TimeLock;
use BiliHelper\Tool\ArrayToTextTable;

class Statistics
{
    use TimeLock;

    private static array $push_list = [];
    private static array $join_list = [];
    private static array $success_list = [];
    private static array $profit_list = [];

    /**
     * @use run
     * @todo 统计开关 统计时间间隔  统计类型
     */
    public static function run(): void
    {
        if (self::getLock() > time()) {
            return;
        }
        self::outputResult();
        self::setLock(20 * 60);
    }

    /**
     * @use 添加推送
     * @param string $key
     * @param int $num
     * @return bool
     */
    public static function addPushList(string $key, int $num = 1): bool
    {
        self::initKeyValue(self::$push_list, $key);
        self::valIncrease(self::$push_list, $key);
        return true;
    }

    /**
     * @use 添加参与
     * @param string $key
     * @param int $num
     * @return bool
     */
    public static function addJoinList(string $key, int $num = 1): bool
    {
        self::initKeyValue(self::$join_list, $key);
        self::valIncrease(self::$join_list, $key);
        return true;
    }

    /**
     * @use 添加成功
     * @param string $key
     * @param int $num
     * @return bool
     */
    public static function addSuccessList(string $key, int $num = 1): bool
    {
        self::initKeyValue(self::$success_list, $key);
        self::valIncrease(self::$success_list, $key);
        return true;
    }

    /**
     * @use 添加收益
     * @param string $title
     * @param int $num
     * @param int $updated_time
     * @return bool
     */
    public static function addProfitList(string $title, int $num, int $updated_time = 0): bool
    {
        self::initKeyValue(self::$profit_list, $title, 0, 'num');
        self::initKeyValue(self::$profit_list, $title, time(), 'updated_time');
        self::valIncrease(self::$profit_list, $title, $num, 'num');
        self::valReplace(self::$profit_list, $title, time(), 'updated_time');
        return true;
    }

    /**
     * @use 转换时间
     * @param int $the_time
     * @return string
     */
    private static function timeTran(int $the_time): string
    {
        $the_time = $the_time < 1577808000 ? time() : $the_time;
        $t = time() - $the_time + 3;//现在时间-发布时间 获取时间差
        $f = [
            '31536000' => '年',
            '2592000' => '个月',
            '604800' => '星期',
            '86400' => '天',
            '3600' => '小时',
            '60' => '分钟',
            '1' => '秒',
        ];
        foreach ($f as $k => $v) {
            if (0 != $c = floor($t / (int)$k)) {
                return $c . $v . '前';
            }
        }
        return '';
    }

    /**
     * @use 初始化键值
     * @param array $target
     * @param string $key
     * @param int $value
     * @param null $second_key
     * @return bool
     */
    private static function initKeyValue(array &$target, string $key, int $value = 0, $second_key = null): bool
    {
        if (!array_key_exists(self::getTodayKey(), $target)) {
            $target[self::getTodayKey()] = [];
        }
        if (!array_key_exists($key, $target[self::getTodayKey()])) {
            $target[self::getTodayKey()][$key] = is_null($second_key) ? $value : [];
        }
        if (!is_null($second_key) && !array_key_exists($second_key, $target[self::getTodayKey()][$key])) {
            $target[self::getTodayKey()][$key][$second_key] = $value;
        }
        return true;
    }

    /**
     * @use 获取结果
     * @param array $target
     * @param string $key
     * @param null $second_key
     * @return mixed
     */
    private static function getResult(array &$target, string $key, $second_key = null): mixed
    {
        is_null($second_key) ? self::initKeyValue($target, $key) : self::initKeyValue($target, $key, 0, $second_key);
        return is_null($second_key) ? $target[self::getTodayKey()][$key] : $target[self::getTodayKey()][$key][$second_key];
    }

    /**
     * @use 获取所有结果
     * @param array $target
     * @param string $key
     * @param string|null $second_key
     * @return int
     */
    private static function getResults(array &$target, string $key, string $second_key = null): int
    {
        $results = 0;
        is_null($second_key) ? self::initKeyValue($target, $key) : self::initKeyValue($target, $key, 0, $second_key);
        foreach ($target as $item) {
            $skip = is_null($second_key) ? isset($item[$key]) : isset($item[$key][$second_key]);
            if ($skip) {
                is_null($second_key) ? $results += intval($item[$key]) : $results += intval($item[$key][$second_key]);
            }
        }
        return $results;
    }

    /**
     * @use 变量增加
     * @param array $target
     * @param string $key
     * @param int $num
     * @param null $second_key
     * @return bool
     */
    private static function valIncrease(array &$target, string $key, int $num = 1, $second_key = null): bool
    {
        is_null($second_key) ? $target[self::getTodayKey()][$key] += $num : $target[self::getTodayKey()][$key][$second_key] += $num;
        return true;
    }

    /**
     * @use 变量替换
     * @param array $target
     * @param string $key
     * @param null $data
     * @param string $second_key
     * @return bool
     */
    private static function valReplace(array &$target, string $key, $data = null, string $second_key = ''): bool
    {
        is_null($second_key) ? $target[self::getTodayKey()][$key] = $data : $target[self::getTodayKey()][$key][$second_key] = $data;
        return true;
    }

    /**
     * @use 获取 table -> tr -> td
     * @return array
     */
    private static function getTrList(): array
    {
        $tr_list_count = [];
        $tr_list_profit = [];
        // 统计数量
        foreach (self::$push_list as $index => $item) {
            foreach ($item as $key => $val) {
                $td = [
                    '名称 (总计/今日)' => $key,
                    '推送' => self::getResults(self::$push_list, $key) . '/' . self::getResult(self::$push_list, $key),
                    '参与' => self::getResults(self::$join_list, $key) . '/' . self::getResult(self::$join_list, $key),
                    '成功' => self::getResults(self::$success_list, $key) . '/' . self::getResult(self::$success_list, $key),
                ];
                $tr_list_count[] = $td;
            }
        }
        // 收益数量
        foreach (self::$profit_list as $index => $item) {
            foreach ($item as $key => $val) {
                $td = [
                    '名称 (总计/今日)' => explode('-', $key)[0],
                    '数量' => self::getResults(self::$profit_list, $key, 'num') . '/' . self::getResult(self::$profit_list, $key, 'num'),
                    '奖品' => explode('-', $key)[1],
                    '更新时间' => self::timeTran(self::getResult(self::$profit_list, $key, 'updated_time')),
                ];
                $tr_list_profit[] = $td;
            }
        }
        return [self::unique_arr($tr_list_count), self::unique_arr($tr_list_profit)];
    }

    /**
     * @use 二维数组去重
     * @param array $result
     * @return array
     */
    private static function unique_arr(array $result): array
    {
        return array_unique($result, SORT_REGULAR);
    }

    /**
     * @use 数据数组转表格数组
     * @param array $data
     * @return array
     */
    private static function arrayToTable(array $data): array
    {
        $th_list = [];
        if ($data) {
            $renderer = new ArrayToTextTable($data);
            foreach (explode("\n", $renderer->getTable()) as $value) {
                if ($value) {
                    $th_list[] = $value;
                }
            }
        }
        return $th_list;
    }

    /**
     * @use 打印表格
     */
    private static function outputResult(): void
    {
        $arr_tr_list = self::getTrList();
        foreach ($arr_tr_list as $tr_list) {
            $th_list = self::arrayToTable($tr_list);
            foreach ($th_list as $th) {
                Log::notice($th);
            }
        }
    }

    /**
     * @use 获取今日KEY
     * @return string
     */
    private static function getTodayKey(): string
    {
        $ts = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        // 1592668800 -> 5bb4085b4cc25bc0
        return md5($ts);
    }

}