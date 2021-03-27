<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Util;

use JsonDecodeStream\Parser;


trait AllotTasks
{

//    protected static $repository = '';
    protected static $tasks = [];
    protected static $work_status = [
        'work_updated' => null,
        'estimated_time' => null,
        'work_completed' => null,
    ];


    /**
     * @use 加载json数据
     * @return Parser
     */
    protected static function loadJsonData()
    {
        return Parser::fromFile(static::$repository);
    }

    /**
     * @use 提交任务
     * @param string $operation
     * @param \stdClass $act
     * @param bool $time
     * @return bool
     */
    protected static function pushTask(string $operation, \stdClass $act, bool $time = false): bool
    {
        $task = [
            'operation' => $operation,
            'act' => $act,
            'time' => false
        ];
        if ($time) {
            $task['time'] = $time;
        }
        array_push(static::$tasks, $task);
        return true;
    }

    /**
     * @use 拉取任务
     * @return false|mixed
     */
    protected static function pullTask()
    {
        // 任务列表为空
        if (empty(static::$tasks)) {
            return false;
        }
        // 先进先出 弹出一个任务
        $task = array_shift(static::$tasks);
        if ($task['time']) {
            if (is_null(static::$work_status['estimated_time']) || time() < intval(static::$work_status['estimated_time'])) {
                array_unshift(static::$tasks, $task);
            }
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
