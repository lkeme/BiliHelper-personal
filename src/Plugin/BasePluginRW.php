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

namespace Bhp\Plugin;

use Bhp\Log\Log;
use Bhp\Runtime\AppContext;
use Bhp\Runtime\Runtime;
use Bhp\Scheduler\TaskResult;
use Bhp\Util\Resource\BaseResourcePoly;
use Bhp\Util\Exceptions\RequestException;

abstract class BasePluginRW extends BaseResourcePoly
{
    use BasePluginInfo;

    protected ?TaskResult $taskResult = null;

    protected function bootPlugin(Plugin &$plugin, bool $schedulerTask = false): void
    {
        $plugin->register($this, $schedulerTask ? 'runOnce' : 'execute');
    }

    protected function resetTaskResult(): void
    {
        $this->taskResult = null;
    }

    protected function resolveTaskResult(TaskResult $default): TaskResult
    {
        return $this->taskResult ?? $default;
    }

    protected function scheduleAfter(float $seconds, ?string $message = null): TaskResult
    {
        return $this->taskResult = TaskResult::after($seconds, $message);
    }

    protected function retryAfter(float $seconds, ?string $message = null): TaskResult
    {
        return $this->taskResult = TaskResult::retryAfter($seconds, $message);
    }

    protected function retryAfterRequestException(
        RequestException $exception,
        string $label = '请求',
        float $fallbackSeconds = 600.0,
    ): TaskResult {
        $suffix = $exception->getCategory() !== '' ? " [{$exception->getCategory()}]" : '';
        Log::warning("{$label}: {$exception->getMessage()}{$suffix}");

        return $this->retryAfter($fallbackSeconds, $exception->getMessage());
    }

    protected function appContext(): AppContext
    {
        return Runtime::getInstance()->appContext();
    }

    protected function config(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->appContext()->config($key, $default, $type);
    }

    protected function enabled(string $key, bool $default = false): bool
    {
        return $this->appContext()->enabled($key, $default);
    }

    protected function auth(string $key): string
    {
        return $this->appContext()->auth($key);
    }

    protected function setAuth(string $key, mixed $value): void
    {
        $this->appContext()->setAuth($key, $value);
    }
}

