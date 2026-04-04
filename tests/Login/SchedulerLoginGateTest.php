<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Login\LoginPendingFlowStore;
use Bhp\Runtime\AppContext;
use Bhp\Scheduler\ScheduledTask;
use Bhp\Scheduler\Scheduler;
use Bhp\Scheduler\TaskPolicy;
use Bhp\Util\DesignPattern\SingleTon;
use Tests\Support\Assert;

if (!defined('PROFILE_CACHE_PATH')) {
    $cachePath = sys_get_temp_dir() . '/bilihelper-scheduler-login-cache-' . substr(md5((string)__FILE__), 0, 8);
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0777, true);
    }
    define('PROFILE_CACHE_PATH', $cachePath);
}
if (!defined('PROFILE_CONFIG_PATH')) {
    $configPath = sys_get_temp_dir() . '/bilihelper-scheduler-login-config-' . substr(md5((string)__FILE__), 0, 8) . DIRECTORY_SEPARATOR;
    if (!is_dir($configPath)) {
        mkdir($configPath, 0777, true);
    }
    define('PROFILE_CONFIG_PATH', $configPath);
}
if (!defined('PROFILE_LOG_PATH')) {
    $logPath = sys_get_temp_dir() . '/bilihelper-scheduler-login-log-' . substr(md5((string)__FILE__), 0, 8) . DIRECTORY_SEPARATOR;
    if (!is_dir($logPath)) {
        mkdir($logPath, 0777, true);
    }
    define('PROFILE_LOG_PATH', $logPath);
}

$context = new AppContext();
$pendingFlowStore = new LoginPendingFlowStore();
clearSchedulerLoginState($context, $pendingFlowStore);
resetSingleton(Scheduler::class);

$scheduler = Scheduler::getInstance();
$holdMethod = privateMethod(Scheduler::class, 'shouldHoldTaskForLoginPendingFlow');
$businessTask = new ScheduledTask('Demo', 'Demo', 2000, 60.0, TaskPolicy::SKIP, 1, 30.0);
$loginTask = new ScheduledTask('Login', 'Login', 1001, 7200.0, TaskPolicy::SERIALIZE, 1, 180.0, 0.0, false, true);

Assert::true($holdMethod->invoke($scheduler, $businessTask), '未登录态时业务任务应被登录门闩阻塞。');
Assert::false($holdMethod->invoke($scheduler, $loginTask), '未登录态时 Login 任务不应被门闩阻塞。');

foreach ([
    'access_token' => 'access-token',
    'refresh_token' => 'refresh-token',
    'cookie' => 'SESSDATA=test;bili_jct=csrfcsrfcsrfcsrfcsrfcsrfcsrf12;DedeUserID=123456;DedeUserID__ckMd5=1234567890abcdef;',
    'uid' => '123456',
    'csrf' => 'csrfcsrfcsrfcsrfcsrfcsrfcsrf12',
] as $key => $value) {
    $context->setAuth($key, $value);
}

$scheduler->registerPlugins([
    [
        'hook' => 'Login',
        'name' => 'Login',
        'priority' => 1001,
        'cycle' => '2(小时)',
        'interval_seconds' => 7200,
        'max_concurrency' => 1,
        'overrun_policy' => TaskPolicy::SERIALIZE,
        'timeout_seconds' => 180,
        'bootstrap_first' => true,
    ],
    [
        'hook' => 'Demo',
        'name' => 'Demo',
        'priority' => 2000,
        'cycle' => '1(分钟)',
        'interval_seconds' => 60,
        'max_concurrency' => 1,
        'overrun_policy' => TaskPolicy::SKIP,
        'timeout_seconds' => 30,
    ],
]);

$tasksProperty = privateProperty(Scheduler::class, 'tasks');
/** @var array<string, ScheduledTask> $tasks */
$tasks = $tasksProperty->getValue($scheduler);
$tasks['Login']->nextRunAtNs = 9999999.0;

$requestRecovery = privateMethod(Scheduler::class, 'requestLoginRecovery');
$requestRecovery->invoke($scheduler, $tasks['Demo'], 1234.0, '账号未登录');

Assert::same(1234.0, $tasks['Login']->nextRunAtNs, '业务插件触发 NoLoginException 时应立即重排 Login。');
Assert::true($holdMethod->invoke($scheduler, $tasks['Demo']), '请求登录恢复后业务任务应被全局登录门闩阻塞。');

$refreshRecovery = privateMethod(Scheduler::class, 'refreshLoginRecoveryState');
$refreshRecovery->invoke($scheduler, $tasks['Login']);
Assert::false($holdMethod->invoke($scheduler, $tasks['Demo']), 'Login 恢复且 auth_ready 时应解除全局门闩。');

clearSchedulerLoginState($context, $pendingFlowStore);
resetSingleton(Scheduler::class);

function clearSchedulerLoginState(AppContext $context, LoginPendingFlowStore $pendingFlowStore): void
{
    foreach ($context->authKeys() as $key) {
        $context->setAuth($key, '');
    }

    $pendingFlowStore->clear();
}

function resetSingleton(string $className): void
{
    $instances = privateProperty(SingleTon::class, '_instances');
    $current = $instances->getValue();
    if (!is_array($current)) {
        $current = [];
    }

    unset($current[$className]);
    $instances->setValue(null, $current);
}

function privateMethod(string $className, string $methodName): \ReflectionMethod
{
    $reflection = new ReflectionClass($className);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method;
}

function privateProperty(string $className, string $propertyName): \ReflectionProperty
{
    $reflection = new ReflectionClass($className);
    $property = $reflection->getProperty($propertyName);
    $property->setAccessible(true);

    return $property;
}
