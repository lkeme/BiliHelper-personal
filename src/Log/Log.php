<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
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
use Bhp\Runtime\AppContext;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Log
{
    private static ?self $current = null;

    /**
     * @var Logger|null
     */
    protected ?Logger $_logger = null;

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $contextStacks = [];

    public function __construct(
        private readonly AppContext $context,
    ) {
        self::$current = $this;
    }

    /**
     * @return void
     */
    /**
     * 初始化日志服务
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        if (!$this->_logger instanceof Logger) {
            $logger = new Logger('BHP');
            // 日志等级 DEBUG ?? INFO
            $handler = new StreamHandler('php://stdout', $this->enabled('debug') ? Logger::DEBUG : Logger::INFO);
            $handler->setFormatter(new ColoredLineFormatter(
                '[%datetime%] %level_name%: %message%',
                'Y-m-d H:i:s',
                false,
                true
            ));
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
        $context = $this->mergedContext($context);
        // 拼装信息内容
        $message = $this->prefix() . $this->callerLabel($context) . $msg;
        // 写入文件
        $this->writeLog($level, $message);

        // DEBUG数据单独处理/不需要回调
        if ($level == 'DEBUG') {
            $this->getLogger()->debug($message);
            return;
        }
        // 匹配等级
        switch ($level) {
            case 'INFO':
                $level_id = Logger::INFO;
                $this->getLogger()->info($message);
                break;
            case 'NOTICE':
                $level_id = Logger::NOTICE;
                $this->getLogger()->notice($message);
                break;
            case 'WARNING':
                $level_id = Logger::WARNING;
                $this->getLogger()->warning($message);
                break;
            case 'ERROR':
                $level_id = Logger::ERROR;
                $this->getLogger()->error($message);
                break;
            default:
                $level_id = Logger::CRITICAL;
                $this->getLogger()->critical($message);
                break;
        }
        // 回调
        $this->callback($level_id, $level, $message);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function withContext(array $context, callable $callback): mixed
    {
        return self::service()->withScopedContext($context, $callback);
    }

    /**
     * 错误
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function error(mixed $message, array $context = []): void
    {
        self::service()->recordError($message, $context);
    }

    /**
     * 警告
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function warning(mixed $message, array $context = []): void
    {
        self::service()->recordWarning($message, $context);
    }

    /**
     * 提醒
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function notice(mixed $message, array $context = []): void
    {
        self::service()->recordNotice($message, $context);
    }

    /**
     * 信息
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function info(mixed $message, array $context = []): void
    {
        self::service()->recordInfo($message, $context);
    }

    /**
     * 调试
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public static function debug(mixed $message, array $context = []): void
    {
        self::service()->recordDebug($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function withScopedContext(array $context, callable $callback): mixed
    {
        $this->pushContext($context);

        try {
            return $callback();
        } finally {
            $this->popContext();
        }
    }

    public function recordError(mixed $message, array $context = []): void
    {
        $this->log('ERROR', (string)$message, $context);
    }

    public function recordWarning(mixed $message, array $context = []): void
    {
        $this->log('WARNING', (string)$message, $context);
    }

    public function recordNotice(mixed $message, array $context = []): void
    {
        $this->log('NOTICE', (string)$message, $context);
    }

    public function recordInfo(mixed $message, array $context = []): void
    {
        $this->log('INFO', (string)$message, $context);
    }

    public function recordDebug(mixed $message, array $context = []): void
    {
        $this->log('DEBUG', (string)$message, $context);
    }

    /**
     * 堆栈
     * @return string
     */
    protected function backtrace(): string
    {
        $backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtraces as $frame) {
            $file = $frame['file'] ?? null;
            if (!is_string($file) || $file === '') {
                continue;
            }

            $filename = pathinfo(basename($file), PATHINFO_FILENAME);
            if ($filename === basename(str_replace('\\', '/', __CLASS__))) {
                continue;
            }

            return '(' . $filename . ') => ';
        }

        return '(Log) => ';
    }

    /**
     * 前缀
     * @return string
     */
    protected function prefix(): string
    {
        if ($this->config('print.multiple', false, 'bool')) {
            return sprintf("[%s]", $this->config('print.user_identity') ?? $this->config('login_account.username'));
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
        if ($this->enabled('log')) {
            if ($type == 'DEBUG' && !$this->enabled('debug')) {
                return;
            }
            $filename = $this->context->profileContext()->logPath() . $this->config('login_account.username') . ".log";
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
        $callback_level = $this->config('log.callback_level') ?? Logger::ERROR;
        if ($levelId >= $callback_level) {
            // Startup failed, given null value
            if (is_null($this->config('log.callback'))) return;
            //
            $url = str_replace('{account}', $this->prefix(), (string)$this->config('log.callback'));
            $url = str_replace('{level}', $level, $url);
            $url = str_replace('{message}', urlencode($message), $url);
            //
            $this->context->request()->sendSingle('get', str_replace(' ', '%20', $url));
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function pushContext(array $context): void
    {
        $key = $this->currentContextKey();
        $this->contextStacks[$key] ??= [];
        $this->contextStacks[$key][] = $context;
    }

    protected function popContext(): void
    {
        $key = $this->currentContextKey();
        if (!isset($this->contextStacks[$key])) {
            return;
        }

        array_pop($this->contextStacks[$key]);
        if ($this->contextStacks[$key] === []) {
            unset($this->contextStacks[$key]);
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function mergedContext(array $context): array
    {
        $merged = [];
        foreach ($this->contextStacks[$this->currentContextKey()] ?? [] as $item) {
            $merged = array_replace($merged, $item);
        }

        return array_replace($merged, $context);
    }

    protected function currentContextKey(): string
    {
        $fiber = \Fiber::getCurrent();
        if ($fiber === null) {
            return 'main';
        }

        return 'fiber_' . spl_object_id($fiber);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function callerLabel(array $context): string
    {
        $caller = trim((string)($context['caller'] ?? $context['plugin'] ?? ''));
        if ($caller === '') {
            return $this->backtrace();
        }

        return '(' . $caller . ') => ';
    }

    protected function config(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->context->config($key, $default, $type);
    }

    protected function enabled(string $key, bool $default = false): bool
    {
        return (bool)$this->config($key . '.enable', $default, 'bool');
    }

    private static function service(): self
    {
        if (self::$current instanceof self) {
            return self::$current;
        }

        throw new \RuntimeException('Log has not been bootstrapped.');
    }

}


