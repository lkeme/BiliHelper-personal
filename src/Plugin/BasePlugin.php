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

    /**
     * 初始化插件
     * @param Plugin $plugin
     * @param bool $schedulerTask
     * @return void
     */
    protected function bootPlugin(Plugin $plugin, bool $schedulerTask = false): void
    {
        $this->pluginService = $plugin;
        $this->context = $plugin->appContext();
        $this->notice = $plugin->notice();
        $this->log = $plugin->log();
        $plugin->register($this, $schedulerTask ? 'runOnce' : 'execute');
    }

    /**
     * 处理reset任务结果
     * @return void
     */
    protected function resetTaskResult(): void
    {
        $this->taskResult = null;
    }

    /**
     * 解析任务结果
     * @param TaskResult $default
     * @return TaskResult
     */
    protected function resolveTaskResult(TaskResult $default): TaskResult
    {
        return $this->taskResult ?? $default;
    }

    /**
     * 处理scheduleAfter
     * @param float $seconds
     * @param string $message
     * @return TaskResult
     */
    protected function scheduleAfter(float $seconds, ?string $message = null): TaskResult
    {
        return $this->taskResult = TaskResult::after($seconds, $message);
    }

    /**
     * 处理重试After
     * @param float $seconds
     * @param string $message
     * @return TaskResult
     */
    protected function retryAfter(float $seconds, ?string $message = null): TaskResult
    {
        return $this->taskResult = TaskResult::retryAfter($seconds, $message);
    }

    /**
     * 处理重试After请求Exception
     * @param RequestException $exception
     * @param string $label
     * @param float $fallbackSeconds
     * @return TaskResult
     */
    protected function retryAfterRequestException(
        RequestException $exception,
        string $label = '请求',
        float $fallbackSeconds = 600.0,
    ): TaskResult {
        $suffix = $exception->getCategory() !== '' ? " [{$exception->getCategory()}]" : '';
        $this->warning("{$label}: {$exception->getMessage()}{$suffix}");

        return $this->retryAfter($fallbackSeconds, $exception->getMessage());
    }

    /**
     * 处理应用上下文
     * @return AppContext
     */
    protected function appContext(): AppContext
    {
        if (!$this->context instanceof AppContext) {
            throw new LogicException('Plugin context has not been bootstrapped.');
        }

        return $this->context;
    }

    /**
     * 处理配置
     * @param string $key
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    protected function config(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->appContext()->config($key, $default, $type);
    }

    /**
     * 处理enabled
     * @param string $key
     * @param bool $default
     * @return bool
     */
    protected function enabled(string $key, bool $default = false): bool
    {
        return $this->appContext()->enabled($key, $default);
    }

    /**
     * 处理认证
     * @param string $key
     * @return string
     */
    protected function auth(string $key): string
    {
        return $this->appContext()->auth($key);
    }

    /**
     * 处理csrf
     * @return string
     */
    protected function csrf(): string
    {
        return $this->appContext()->csrf();
    }

    /**
     * 处理UID
     * @return string
     */
    protected function uid(): string
    {
        return $this->appContext()->uid();
    }

    /**
     * 处理sid
     * @return string
     */
    protected function sid(): string
    {
        return $this->appContext()->sid();
    }

    /**
     * 设置认证
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function setAuth(string $key, mixed $value): void
    {
        $this->appContext()->setAuth($key, $value);
    }

    /**
     * 处理过滤Words
     * @param string $key
     * @param mixed $default
     * @param string $type
     * @return mixed
     */
    protected function filterWords(string $key, mixed $default = null, string $type = 'default'): mixed
    {
        return $this->appContext()->filterWords($key, $default, $type);
    }

    /**
     * 处理请求
     * @return Request
     */
    protected function request(): Request
    {
        return $this->appContext()->request();
    }

    /**
     * 请求Get
     * @param string $os
     * @param string $url
     * @param array $params
     * @param array $headers
     * @param float $timeout
     * @return string
     */
    protected function requestGet(string $os, string $url, array $params = [], array $headers = [], float $timeout = 30.0): string
    {
        return $this->request()->getText($os, $url, $params, $headers, $timeout);
    }

    /**
     * 处理缓存
     * @return Cache
     */
    protected function cache(): Cache
    {
        return $this->appContext()->cache();
    }

    /**
     * 初始化ialize缓存
     * @param string $scope
     * @return void
     */
    protected function initializeCache(string $scope): void
    {
        $this->cache()->initializeScope($scope);
    }

    /**
     * 处理缓存Get
     * @param string $key
     * @param string $scope
     * @param mixed $default
     * @return mixed
     */
    protected function cacheGet(string $key, string $scope, mixed $default = null): mixed
    {
        $value = $this->cache()->pull($key, $scope);

        return $value === null || $value === false ? $default : $value;
    }

    /**
     * 处理缓存设置
     * @param string $key
     * @param mixed $value
     * @param string $scope
     * @return void
     */
    protected function cacheSet(string $key, mixed $value, string $scope): void
    {
        $this->cache()->put($key, $value, $scope);
    }

    /**
     * 处理用户Profiles
     * @return UserProfileService
     */
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

    /**
     * 处理插件窗口StartAt
     * @return string
     */
    protected function pluginWindowStartAt(): string
    {
        return $this->pluginScheduleTime('start');
    }

    /**
     * 处理插件窗口EndAt
     * @return string
     */
    protected function pluginWindowEndAt(): string
    {
        return $this->pluginScheduleTime('end');
    }

    /**
     * 处理下次插件Start任务结果
     * @param int $randomMinMinutes
     * @param int $randomMaxMinutes
     * @param bool $nextDay
     * @param string $message
     * @return TaskResult
     */
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

    /**
     * 处理error
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function error(string $message, array $context = []): void
    {
        $this->logService()->recordError($message, $context);
    }

    /**
     * 处理warning
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function warning(string $message, array $context = []): void
    {
        $this->logService()->recordWarning($message, $context);
    }

    /**
     * 处理通知
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function notice(string $message, array $context = []): void
    {
        $this->logService()->recordNotice($message, $context);
    }

    /**
     * 处理信息
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function info(string $message, array $context = []): void
    {
        $this->logService()->recordInfo($message, $context);
    }

    /**
     * 处理debug
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function debug(string $message, array $context = []): void
    {
        $this->logService()->recordDebug($message, $context);
    }

    /**
     * 处理通知
     * @param string $type
     * @param string $message
     * @return void
     */
    protected function notify(string $type, string $message = ''): void
    {
        if (!$this->notice instanceof Notice) {
            throw new LogicException('Plugin notice service has not been bootstrapped.');
        }

        $this->notice->publish($type, $message);
    }

    /**
     * 处理日志服务
     * @return Log
     */
    protected function logService(): Log
    {
        if (!$this->log instanceof Log) {
            throw new LogicException('Plugin log service has not been bootstrapped.');
        }

        return $this->log;
    }

    /**
     * 处理插件Schedule时间
     * @param string $key
     * @return string
     */
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

