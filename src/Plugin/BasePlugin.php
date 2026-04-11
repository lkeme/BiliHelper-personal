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

use Bhp\Cache\Cache;
use Bhp\Log\Log;
use Bhp\Notice\Notice;
use Bhp\Request\Request;
use Bhp\Runtime\AppContext;
use Bhp\Scheduler\TaskResult;
use Bhp\User\UserProfileService;
use Bhp\Util\Exceptions\RequestException;
use LogicException;

abstract class BasePlugin
{
    protected ?TaskResult $taskResult = null;
    private ?Plugin $pluginService = null;
    private ?AppContext $context = null;
    private ?Notice $notice = null;
    private ?Log $log = null;

    protected function bootPlugin(Plugin $plugin, bool $schedulerTask = false): void
    {
        $this->pluginService = $plugin;
        $this->context = $plugin->appContext();
        $this->notice = $plugin->notice();
        $this->log = $plugin->log();
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
        $this->warning("{$label}: {$exception->getMessage()}{$suffix}");

        return $this->retryAfter($fallbackSeconds, $exception->getMessage());
    }

    protected function appContext(): AppContext
    {
        if (!$this->context instanceof AppContext) {
            throw new LogicException('Plugin context has not been bootstrapped.');
        }

        return $this->context;
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

    protected function csrf(): string
    {
        return $this->appContext()->csrf();
    }

    protected function uid(): string
    {
        return $this->appContext()->uid();
    }

    protected function sid(): string
    {
        return $this->appContext()->sid();
    }

    protected function setAuth(string $key, mixed $value): void
    {
        $this->appContext()->setAuth($key, $value);
    }

    protected function filterWords(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->appContext()->filterWords($key, $default, $type);
    }

    protected function request(): Request
    {
        return $this->appContext()->request();
    }

    protected function requestGet(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->request()->getText($os, $url, $params, $headers, $timeout);
    }

    protected function cache(): Cache
    {
        return $this->appContext()->cache();
    }

    protected function initializeCache(string $scope): void
    {
        $this->cache()->initializeScope($scope);
    }

    protected function cacheGet(string $key, string $scope, mixed $default = null): mixed
    {
        $value = $this->cache()->pull($key, $scope);

        return $value === null ? $default : $value;
    }

    protected function cacheSet(string $key, mixed $value, string $scope): void
    {
        $this->cache()->put($key, $value, $scope);
    }

    protected function userProfiles(): UserProfileService
    {
        return $this->appContext()->userProfileService();
    }

    /**
     * @return array<string, mixed>
     */
    protected function pluginDefinition(): array
    {
        if (!$this->pluginService instanceof Plugin) {
            throw new LogicException('Plugin service has not been bootstrapped.');
        }

        return $this->pluginService->pluginDefinitionForClass(static::class);
    }

    protected function pluginWindowStartAt(): string
    {
        return $this->pluginScheduleTime('start');
    }

    protected function pluginWindowEndAt(): string
    {
        return $this->pluginScheduleTime('end');
    }

    protected function nextPluginStartTaskResult(
        int $randomMinMinutes = 0,
        int $randomMaxMinutes = 0,
        bool $nextDay = false,
        ?string $message = null,
    ): TaskResult {
        [$hour, $minute, $second] = $this->parseClockTime($this->pluginWindowStartAt());

        return $nextDay
            ? TaskResult::nextDayAt($hour, $minute, $second, $randomMinMinutes, $randomMaxMinutes, $message)
            : TaskResult::nextAt($hour, $minute, $second, $randomMinMinutes, $randomMaxMinutes, $message);
    }

    protected function error(string $message, array $context = []): void
    {
        $this->logService()->recordError($message, $context);
    }

    protected function warning(string $message, array $context = []): void
    {
        $this->logService()->recordWarning($message, $context);
    }

    protected function notice(string $message, array $context = []): void
    {
        $this->logService()->recordNotice($message, $context);
    }

    protected function info(string $message, array $context = []): void
    {
        $this->logService()->recordInfo($message, $context);
    }

    protected function debug(string $message, array $context = []): void
    {
        $this->logService()->recordDebug($message, $context);
    }

    protected function notify(string $type, string $message = ''): void
    {
        if (!$this->notice instanceof Notice) {
            throw new LogicException('Plugin notice service has not been bootstrapped.');
        }

        $this->notice->publish($type, $message);
    }

    protected function logService(): Log
    {
        if (!$this->log instanceof Log) {
            throw new LogicException('Plugin log service has not been bootstrapped.');
        }

        return $this->log;
    }

    private function pluginScheduleTime(string $key): string
    {
        $value = trim((string)($this->pluginDefinition()[$key] ?? ''));
        if ($value === '') {
            throw new LogicException("Plugin schedule field {$key} is not configured.");
        }

        return $value;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function parseClockTime(string $time): array
    {
        $chunks = explode(':', trim($time));

        return [
            (int)($chunks[0] ?? 0),
            (int)($chunks[1] ?? 0),
            (int)($chunks[2] ?? 0),
        ];
    }
}

