<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2024 ~ 2025
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\TimeLock;

use Bhp\Schedule\Schedule;
use Bhp\Util\DesignPattern\SingleTon;

class TimeLock extends SingleTon
{
    /**
     * @var array|null
     */
    protected ?array $locks = [];

    /**
     * @return void
     */
    public function init(): void
    {

    }

    /**
     * 初始化时间锁
     * @param int $times
     * @param bool $status
     * @return void
     */
    public static function initTimeLock(int $times = 0, bool $status = false): void
    {
        $class_name = self::getInstance()->getCallClassName();
        if (!array_key_exists($class_name, self::getInstance()->locks)) {
            self::getInstance()->locks[$class_name] = ['times' => $times, 'pause' => $status];
        }
    }

    /**
     * 设置暂停状态
     * @param bool $status
     * @return void
     */
    public static function setPause(bool $status = false): void
    {
        $class_name = self::getInstance()->getCallClassName();
        self::getInstance()->locks[$class_name]['pause'] = $status;
    }

    /**
     * 获取暂停状态
     * @return bool
     */
    public static function getPause(): bool
    {
        return self::getInstance()->getLock()['pause'];
    }

    /**
     * 设置计时器
     * @param int $times
     * @return void
     */
    public static function setTimes(int $times): void
    {
        $class_name = self::getInstance()->getCallClassName();
        self::getInstance()->locks[$class_name]['times'] = time() + $times;
        //
        Schedule::set($class_name, self::getInstance()->locks[$class_name]['times']);
    }

    /**
     * 获取计时器
     * @return int
     */
    public static function getTimes(): int
    {
        $class_name = self::getInstance()->getCallClassName();
        return Schedule::get($class_name);
        // return self::getInstance()->getLock()['times'];
    }

    /**
     * @return array
     */
    protected function getLock(): array
    {
        $class_name = $this->getCallClassName();
        if (!array_key_exists($class_name, $this->locks)) {
            failExit("当前类 $class_name 并未初始化时间锁");
        }
        return $this->locks[$class_name];
    }

//    /**
//     * used in Amp loop Delayed
//     * @param int $times
//     * @return Delayed
//     */
//    public static function Delayed(int $times=1000): Delayed
//    {
//        return new Delayed($times);
//    }

    /**
     * 定时
     * @param int $hour 时
     * @param int $minute 分
     * @param int $seconds 秒
     * @param bool $random 随机一个小时内
     * @return int
     */
    public static function timing(int $hour, int $minute = 0, int $seconds = 0, bool $random = false): int
    {
        $time = strtotime('today') + ($hour * 60 * 60) + ($minute * 60) + ($seconds);
        if ($time > time()) {
            $timing = strtotime('today') + ($hour * 60 * 60) + ($minute * 60) + ($seconds) - time();
        } else {
            $timing = strtotime('tomorrow') + ($hour * 60 * 60) + ($minute * 60) + ($seconds) - time();
        }
        return $random ? $timing + mt_rand(1, 60) * 60 : $timing;
    }

    /**
     * 判断是否在时间内
     * @param string $first_time
     * @param string $second_time
     * @return bool
     */
    public static function inTime(string $first_time, string $second_time): bool
    {
        #判断当前时间是否在时间段内，如果是，则执行
        $Day = date('Y-m-d ', time());
        $timeBegin = strtotime($Day . $first_time);
        $timeEnd = strtotime($Day . $second_time);
        $curr_time = time();
        if ($curr_time >= $timeBegin && $curr_time <= $timeEnd) {
            return true;
        }
        return false;
    }

    /**
     * 判断是否在时间内
     * @param string $start
     * @param string $end
     * @return bool
     */
    public static function isWithinTimeRange(string $start, string $end): bool
    {
        // date_default_timezone_set('Asia/Shanghai');
        $startTime = strtotime(date($start));
        $endTime = strtotime(date($end));
        $nowTime = time();
        //
        return $nowTime >= $startTime && $nowTime <= $endTime;
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

//    /**
//     * 暂停
//     */
//    public static function pauseLock(): void
//    {
//        // 备份几种获取方式 get_called_class()
//        // basename(str_replace('\\', '/', $class));
//        // substr(strrchr($class, "\\"), 1);
//        // substr($class, strrpos($class, '\\') + 1);
//        // array_pop(explode('\\', $class));
////        Schedule::triggerRefused((new ReflectionClass(static::class))->getShortName());
//    }


}
