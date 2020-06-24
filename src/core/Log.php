<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2020 ~ 2021
 */

namespace BiliHelper\Core;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use function GuzzleHttp\Psr7\str;

class Log
{
    protected static $instance;

    static public function getLogger()
    {
        if (!self::$instance) {
            self::configureInstance();
        }
        return self::$instance;
    }

    protected static function configureInstance()
    {
        $logger = new Logger('BH');
        $handler = new StreamHandler('php://stdout', getenv('APP_DEBUG') == 'true' ? Logger::DEBUG : Logger::INFO);
        $handler->setFormatter(new ColoredLineFormatter());
        $logger->pushHandler($handler);
        self::$instance = $logger;
    }

    private static function prefix()
    {
        if (getenv('APP_MULTIPLE') == 'true') {
            return '[' . (empty($t = getenv('APP_USER_IDENTITY')) ? getenv('APP_USER') : $t) . ']';
        }
        return '';
    }

    private static function writeLog($type, $message)
    {
        if (getenv('APP_WRITELOG') == 'true') {
            $path = './' . getenv("APP_WRITELOGPATH") . '/';
            if (!file_exists($path)) {
                mkdir($path);
                chmod($path, 0777);
            }
            $filename = $path . getenv('APP_USER') . ".log";
            $date = date('[Y-m-d H:i:s] ');
            $data = $date . ' Log.' . $type . ' ' . $message . PHP_EOL;
            file_put_contents($filename, $data, FILE_APPEND);
        }
        return;
    }

    public static function debug($message, array $context = [])
    {
        self::writeLog('DEBUG', $message);
        self::getLogger()->addDebug($message, $context);
    }

    public static function info($message, array $context = [])
    {
        $message = self::prefix() . $message;
        self::writeLog('INFO', $message);
        self::getLogger()->addInfo($message, $context);
        self::callback(Logger::INFO, 'INFO', $message);
    }

    public static function notice($message, array $context = [])
    {
        $message = self::prefix() . $message;
        self::writeLog('NOTICE', $message);
        self::getLogger()->addNotice($message, $context);
        self::callback(Logger::NOTICE, 'NOTICE', $message);
    }

    public static function warning($message, array $context = [])
    {
        $message = self::prefix() . $message;
        self::writeLog('WARNING', $message);
        self::getLogger()->addWarning($message, $context);
        self::callback(Logger::WARNING, 'WARNING', $message);
    }

    public static function error($message, array $context = [])
    {
        $message = self::prefix() . $message;
        self::writeLog('ERROR', $message);
        self::getLogger()->addError($message, $context);
        self::callback(Logger::ERROR, 'ERROR', $message);
    }

    public static function callback($levelId, $level, $message)
    {
        $callback_level = (('APP_CALLBACK_LEVEL') == '') ? (Logger::ERROR) : intval(getenv('APP_CALLBACK_LEVEL'));
        if ($levelId >= $callback_level) {
            $url = str_replace('{account}', self::prefix(), getenv('APP_CALLBACK'));
            $url = str_replace('{level}', $level, $url);
            $url = str_replace('{message}', urlencode($message), $url);
            Curl::request('get', str_replace(' ', '%20', $url));
        }
    }
}