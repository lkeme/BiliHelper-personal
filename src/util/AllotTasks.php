<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Util;

use stdClass;
use PhpPkg\Config\ConfigBox;


trait AllotTasks
{

//    protected static $repository = '';
    protected static array $tasks = [];
    protected static array $work_status = [
        'work_updated' => null,
        'estimated_time' => null,
        'work_completed' => null,
    ];

    /**
     * @use 加载json数据
     */
    protected static function loadJsonData(): ConfigBox
    {
        return ConfigBox::newFromFile(static::$repository);
    }

    /**
     * @use 提交任务
     * @param string $operation
     * @param stdClass $act
     * @param bool $time
     * @return bool
     */
    protected static function pushTask(string $operation, stdClass $act, bool $time = false): bool
    {
        $task = [
            'operation' => $operation,
            'act' => $act,
            'time' => $time
        ];
        static::$tasks[] = $task;
        return true;
    }

    /**
     * @use 拉取任务
     * @return mixed
     */
    protected static function pullTask(): mixed
    {
        // 任务列表为空
        if (empty(static::$tasks)) {
            return false;
        }
        // 先进先出 弹出一个任务
        $task = array_shift(static::$tasks);
        // 如果需要时间限制
        if ($task['time']) {
            // 如果预计时间为空 或  时间未到 推回队列
            if (is_null(static::$work_status['estimated_time']) || time() < intval(static::$work_status['estimated_time'])) {
                array_unshift(static::$tasks, $task);
            } else {
                // 不再需要推回时  需要制空 不影响下一个任务操作
                static::$work_status['estimated_time'] = null;
            }
        } else {
            // 切换任务 制空
            static::$work_status['estimated_time'] = null;
        }
        return $task;
    }



//    /**
//     * @use 执行任务
//     * @return bool
//     */
//    abstract protected function workTask(): bool;
//
//
//    /**
//     * @use 分配任务
//     * @return bool
//     */
//    abstract protected function allotTasks(): bool;
}
