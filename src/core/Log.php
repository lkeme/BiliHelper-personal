<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 */

namespace BiliHelper\Core;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

class Log
{
    protected static $instance;

    /**
     * @use 实体
     */
    static public function getLogger()
    {
        if (!self::$instance) {
            self::configureInstance();
        }
        return self::$instance;
    }

    /**
     * @use 单例
     */
    private static function configureInstance(): void
    {
        $logger = new Logger('BH');
        $handler = new StreamHandler('php://stdout', getConf('enable', 'debug') ? Logger::DEBUG : Logger::INFO);
        $handler->setFormatter(new ColoredLineFormatter());
        $logger->pushHandler($handler);
        self::$instance = $logger;
    }

    /**
     * @use 前缀
     * @return string
     */
    private static function prefix(): string
    {
        if (getConf('multiple', 'print')) {
            // return '[' . (getConf('user_identity', 'print') ?? getConf('username', 'login.account')) . ']';
            return sprintf("[%s]", getConf('user_identity', 'print') ?? getConf('username', 'login.account'));
        }
        return '';
    }

    /**
     * @use 写日志
     * @param $type
     * @param $message
     */
    private static function writeLog($type, $message): void
    {
        if (getConf('enable', 'log')) {
            if ($type == 'DEBUG' && !getConf('enable', 'debug')) {
                return;
            }

            $filename = APP_LOG_PATH . getConf('username', 'login.account') . ".log";
            $date = date('[Y-m-d H:i:s] ');
            $data = $date . ' Log.' . $type . ' ' . $message . PHP_EOL;
            file_put_contents($filename, $data, FILE_APPEND);
        }
    }

    /**
     * @use 堆栈
     * @return string
     */
    private static function backtrace(): string
    {
        $backtraces = debug_backtrace();
        return "(" . pathinfo(basename($backtraces[1]['file']))['filename'] . ") => ";
    }

    /**
     * @use 调试
     * @param $message
     * @param array $context
     */
    public static function debug($message, array $context = []): void
    {
        self::writeLog('DEBUG', $message);
        self::getLogger()->addDebug($message, $context);
    }

    /**
     * @use 信息
     * @param $message
     * @param array $context
     */
    public static function info($message, array $context = []): void
    {
        $message = self::prefix() . self::backtrace() . $message;
        self::writeLog('INFO', $message);
        self::getLogger()->addInfo($message, $context);
        self::callback(Logger::INFO, 'INFO', $message);
    }

    /**
     * @use 提醒
     * @param $message
     * @param array $context
     */
    public static function notice($message, array $context = []): void
    {
        $message = self::prefix() . self::backtrace() . $message;
        self::writeLog('NOTICE', $message);
        self::getLogger()->addNotice($message, $context);
        self::callback(Logger::NOTICE, 'NOTICE', $message);
    }

    /**
     * @use 警告
     * @param $message
     * @param array $context
     */
    public static function warning($message, array $context = []): void
    {
        $message = self::prefix() . self::backtrace() . $message;
        self::writeLog('WARNING', $message);
        self::getLogger()->addWarning($message, $context);
        self::callback(Logger::WARNING, 'WARNING', $message);
    }

    /**
     * @use 错误
     * @param $message
     * @param array $context
     */
    public static function error($message, array $context = []): void
    {
        $message = self::prefix() . self::backtrace() . $message;
        self::writeLog('ERROR', $message);
        self::getLogger()->addError($message, $context);
        self::callback(Logger::ERROR, 'ERROR', $message);
    }

    /**
     * @use 回调
     * @param $levelId
     * @param $level
     * @param $message
     */
    public static function callback($levelId, $level, $message): void
    {
        // $callback_level = Logger::ERROR ?? getConf('callback_level', 'log');
        $callback_level = getConf('callback_level', 'log') ?? Logger::ERROR;
        if ($levelId >= $callback_level) {
            $url = str_replace('{account}', self::prefix(), getConf('callback', 'log'));
            $url = str_replace('{level}', $level, $url);
            $url = str_replace('{message}', urlencode($message), $url);
            Curl::request('get', str_replace(' ', '%20', $url));
        }
    }
}