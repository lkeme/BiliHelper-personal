<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Log;

use Bhp\Request\Request;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Bhp\Util\DesignPattern\SingleTon;

class Log extends SingleTon
{
    /**
     * @var Logger|null
     */
    protected ?Logger $_logger = null;

    /**
     * @return void
     */
    public function init(): void
    {

    }

    /**
     * 初始化日志服务
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        if (!$this->_logger instanceof Logger) {
            $logger = new Logger('BHP');
            // 日志等级 DEBUG ?? INFO
            $handler = new StreamHandler('php://stdout', getEnable('debug') ? Logger::DEBUG : Logger::INFO);
            $handler->setFormatter(new ColoredLineFormatter(null, null, 'Y-m-d H:i:s'));
            $logger->pushHandler($handler);
            $this->_logger = $logger;
        }
        return $this->_logger;
    }

    /**
     * 通用
     * @param string $level
     * @param string $msg
     * @param array $context
     * @return void
     */
    protected function log(string $level, string $msg, array $context = []): void
    {
        // 拼装信息内容
        $message = $this->prefix() . $this->backtrace() . $msg;
        // 写入文件
        $this->writeLog($level, $message);

        // DEBUG数据单独处理/不需要回调
        if ($level == 'DEBUG') {
            $this->getInstance()->getLogger()->debug($msg, $context);
            return;
        }
        // 匹配等级
        switch ($level) {
            case 'INFO':
                $level_id = Logger::INFO;
                $this->getInstance()->getLogger()->info($message, $context);
                break;
            case 'NOTICE':
                $level_id = Logger::NOTICE;
                $this->getInstance()->getLogger()->notice($message, $context);
                break;
            case 'WARNING':
                $level_id = Logger::WARNING;
                $this->getInstance()->getLogger()->warning($message, $context);
                break;
            case 'ERROR':
                $level_id = Logger::ERROR;
                $this->getInstance()->getLogger()->error($message, $context);
                break;
            default:
                $level_id = Logger::CRITICAL;
                $this->getInstance()->getLogger()->critical($message, $context);
                break;
        }
        // 回调
        $this->callback($level_id, $level, $message);
    }

    /**
     * 错误
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function error(mixed $message, array $context = []): void
    {
        self::getInstance()->log('ERROR', $message, $context);
    }

    /**
     * 警告
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function warning(mixed $message, array $context = []): void
    {
        self::getInstance()->log('WARNING', $message, $context);
    }

    /**
     * 提醒
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function notice(mixed $message, array $context = []): void
    {
        self::getInstance()->log('NOTICE', $message, $context);
    }

    /**
     * 信息
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function info(mixed $message, array $context = []): void
    {
        self::getInstance()->log('INFO', $message, $context);
    }

    /**
     * 调试
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function debug(mixed $message, array $context = []): void
    {
        self::getInstance()->log('DEBUG', $message, $context);
    }

    /**
     * 堆栈
     * @return string
     */
    protected function backtrace(): string
    {
        $backtraces = debug_backtrace();
        return "(" . pathinfo(basename($backtraces[2]['file']))['filename'] . ") => ";
    }

    /**
     * 前缀
     * @return string
     */
    protected function prefix(): string
    {
        if (getConf('print.multiple')) {
            // return '[' . (getConf('print.user_identity') ?? getConf('login_account.username')) . ']';
            return sprintf("[%s]", getConf('print.user_identity') ?? getConf('login_account.username'));
        }
        return '';
    }

    /**
     * 写日志
     * @param string $type
     * @param string $message
     */
    protected function writeLog(string $type, string $message): void
    {
        if (getEnable('log')) {
            if ($type == 'DEBUG' && !getEnable('debug')) {
                return;
            }
            $filename = PROFILE_LOG_PATH . getConf('login_account.username') . ".log";
            $date = date('[Y-m-d H:i:s] ');
            $data = $date . ' Log.' . $type . ' ' . $message . PHP_EOL;
            file_put_contents($filename, $data, FILE_APPEND);
        }
    }

    /**
     * 回调
     * @param int $levelId
     * @param string $level
     * @param mixed $message
     * @return void
     */
    protected function callback(int $levelId, string $level, mixed $message): void
    {
        $callback_level = getConf('log.callback_level') ?? Logger::ERROR;
        if ($levelId >= $callback_level) {
            $url = str_replace('{account}', $this->prefix(), getConf('log.callback'));
            $url = str_replace('{level}', $level, $url);
            $url = str_replace('{message}', urlencode($message), $url);
            //
            Request::single('get', str_replace(' ', '%20', $url));
        }
    }

}

 
 