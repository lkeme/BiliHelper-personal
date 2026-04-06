<?php declare(strict_types=1);

namespace Bhp\Log;

use Bhp\Runtime\AppContext;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Log
{
    protected ?Logger $_logger = null;

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $contextStacks = [];

    public function __construct(
        private readonly AppContext $context,
    ) {
    }

    protected function getLogger(): Logger
    {
        if (!$this->_logger instanceof Logger) {
            $logger = new Logger('BHP');
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

    protected function log(string $level, string $message, array $context = []): void
    {
        $context = $this->mergedContext($context);
        $rendered = $this->prefix() . $this->callerLabel($context) . $message;
        $this->writeLog($level, $rendered);

        if ($level === 'DEBUG') {
            $this->getLogger()->debug($rendered);

            return;
        }

        $levelId = match ($level) {
            'INFO' => Logger::INFO,
            'NOTICE' => Logger::NOTICE,
            'WARNING' => Logger::WARNING,
            'ERROR' => Logger::ERROR,
            default => Logger::CRITICAL,
        };

        match ($level) {
            'INFO' => $this->getLogger()->info($rendered),
            'NOTICE' => $this->getLogger()->notice($rendered),
            'WARNING' => $this->getLogger()->warning($rendered),
            'ERROR' => $this->getLogger()->error($rendered),
            default => $this->getLogger()->critical($rendered),
        };

        $this->callback($levelId, $level, $rendered);
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

    protected function prefix(): string
    {
        if ($this->config('print.multiple', false, 'bool')) {
            return sprintf("[%s]", $this->config('print.user_identity') ?? $this->config('login_account.username'));
        }

        return '';
    }

    protected function writeLog(string $type, string $message): void
    {
        if (!$this->enabled('log')) {
            return;
        }

        if ($type === 'DEBUG' && !$this->enabled('debug')) {
            return;
        }

        $filename = $this->context->profileContext()->logPath() . $this->config('login_account.username') . '.log';
        $date = date('[Y-m-d H:i:s] ');
        $data = $date . ' Log.' . $type . ' ' . $message . PHP_EOL;
        file_put_contents($filename, $data, FILE_APPEND);
    }

    protected function callback(int $levelId, string $level, mixed $message): void
    {
        $callbackLevel = $this->config('log.callback_level') ?? Logger::ERROR;
        if ($levelId < $callbackLevel) {
            return;
        }

        $callbackUrl = $this->config('log.callback');
        if ($callbackUrl === null) {
            return;
        }

        $url = str_replace('{account}', $this->prefix(), (string)$callbackUrl);
        $url = str_replace('{level}', $level, $url);
        $url = str_replace('{message}', urlencode((string)$message), $url);
        $this->context->request()->sendSingle('get', str_replace(' ', '%20', $url));
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
}
