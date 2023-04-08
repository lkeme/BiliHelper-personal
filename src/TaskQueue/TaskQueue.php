<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\TaskQueue;

use Bhp\Util\DesignPattern\SingleTon;

class TaskQueue extends SingleTon
{
    /**
     * @var array|null
     */
    protected ?array $queue = [];

    /**
     * @return void
     */
    public function init(): void
    {

    }

    /**
     * 初始化任务队列
     * @return void
     */
    public static function initTaskQueue(): void
    {
        $class_name = self::getInstance()->getCallClassName();
        if (!array_key_exists($class_name, self::getInstance()->queue)) {
            self::getInstance()->queue[$class_name] = [];
        }
    }

    /**
     * 入队
     * @param bool $status
     * @return void
     */
    public static function enqueue(bool $status = false): void
    {
        $class_name = self::getInstance()->getCallClassName();
        self::getInstance()->queue[$class_name]['pause'] = $status;
    }

    /**
     * 离队
     * @return bool
     */
    public static function dequeue(): bool
    {
        return self::getInstance()->getQueue()['pause'];
    }

    public static function consume()
    {

    }

    public static function clear()
    {

    }


    /**
     * @return array
     */
    protected function getQueue(): array
    {
        $class_name = $this->getCallClassName();
        if (!array_key_exists($class_name, $this->queue)) {
            failExit("当前类 $class_name 并未初始化任务队列");
        }
        return $this->queue[$class_name];
    }

    /**
     * 获取调用者类名
     * @return string
     */
    protected function getCallClassName(): string
    {
        // basename(str_replace('\\', '/', __CLASS__));
        $backtraces = debug_backtrace();
        $temp = pathinfo(basename($backtraces[1]['file']))['filename'];
        //
        if ($temp == basename(str_replace('\\', '/', __CLASS__))) {
            return pathinfo(basename($backtraces[2]['file']))['filename'];
        } else {
            return $temp;
        }
    }

    /**
     * 获取基础CLASS NAME
     * @return string
     */
    protected function getBaseClass(): string
    {
        return basename(str_replace('\\', '/', __CLASS__));
    }
}
