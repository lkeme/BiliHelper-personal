<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

class Task
{
    private static $init = true;
    private static $repository = null;
    private static $instance;

    /**
     * @use 单例
     * @return \BiliHelper\Core\Task
     */
    public static function getInstance(): Task
    {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    /**
     * @use 初始化
     */
    private static function init()
    {
        if (self::$init) {
            // 赋值仓库地址
            if (is_null(self::$repository)) {
                self::$repository = APP_TASK_PATH . 'tasks_' . getConf('username', 'login.account') . '.json';
            }
            // 仓库不存在自动创建
            if (!file_exists(self::$repository)) {
                $fh = fopen(self::$repository, "w");
                fwrite($fh, "{}");
                fclose($fh);
                Log::info('任务排程文件不存在，初始化所有任务。');
            } else {
                Log::info('任务排程文件存在，继续执行所有任务。');
            }
        }
        // 限制初始化
        self::$init = false;
    }

    /**
     * @use 读
     * @return array
     */
    private static function read(): array
    {
        self::init();
        $data = file_get_contents(self::$repository);
        return json_decode($data, true);
    }

    /**
     * @use 写
     * @param array $data
     * @return bool
     */
    private static function write(array $data): bool
    {
        self::init();
        return file_put_contents(self::$repository, json_encode($data));
    }

    /**
     * @use 写入
     * @param string $class
     * @param int $lock
     * @return bool
     */
    public static function _setLock(string $class, int $lock): bool
    {
        $data = self::read();
        $data[$class] = $lock;
        return self::write($data);
    }

    /**
     * @use 读取
     * @param string $class
     * @return int
     */
    public static function _getLock(string $class): int
    {
        $data = self::read();
        if (array_key_exists($class, $data)) {
            return $data[$class];
        }
        return 0;
    }

}