<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Login\LoginGateStateService;
use Bhp\Login\LoginPendingFlowStore;
use Bhp\Runtime\AppContext;
use Tests\Support\Assert;

if (!defined('PROFILE_CACHE_PATH')) {
    $cachePath = sys_get_temp_dir() . '/bilihelper-login-gate-cache-' . substr(md5((string)__FILE__), 0, 8);
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0777, true);
    }
    define('PROFILE_CACHE_PATH', $cachePath);
}
if (!defined('PROFILE_CONFIG_PATH')) {
    $configPath = sys_get_temp_dir() . '/bilihelper-login-gate-config-' . substr(md5((string)__FILE__), 0, 8) . DIRECTORY_SEPARATOR;
    if (!is_dir($configPath)) {
        mkdir($configPath, 0777, true);
    }
    define('PROFILE_CONFIG_PATH', $configPath);
}
if (!defined('PROFILE_LOG_PATH')) {
    $logPath = sys_get_temp_dir() . '/bilihelper-login-gate-log-' . substr(md5((string)__FILE__), 0, 8) . DIRECTORY_SEPARATOR;
    if (!is_dir($logPath)) {
        mkdir($logPath, 0777, true);
    }
    define('PROFILE_LOG_PATH', $logPath);
}

$context = new AppContext();
$pendingFlowStore = new LoginPendingFlowStore();
clearLoginState($context, $pendingFlowStore);

$service = new LoginGateStateService($context, $pendingFlowStore);
Assert::false($service->authReady(), '缺少认证信息时 authReady 应为 false。');
Assert::same('missing_auth', $service->state(), '缺少认证信息时 state 应为 missing_auth。');
Assert::true($service->shouldBlockBusinessTasks(), '缺少认证信息时应阻塞业务任务。');

foreach ([
    'access_token' => 'access-token',
    'refresh_token' => 'refresh-token',
    'cookie' => 'SESSDATA=test;bili_jct=csrfcsrfcsrfcsrfcsrfcsrfcsrf12;DedeUserID=123456;DedeUserID__ckMd5=1234567890abcdef;',
    'uid' => '123456',
    'csrf' => 'csrfcsrfcsrfcsrfcsrfcsrfcsrf12',
] as $key => $value) {
    $context->setAuth($key, $value);
}

Assert::true($service->authReady(), '关键认证字段齐全时 authReady 应为 true。');
Assert::same('auth_ready', $service->state(), '关键认证字段齐全时 state 应为 auth_ready。');
Assert::false($service->shouldBlockBusinessTasks(), '认证就绪时不应阻塞业务任务。');

$pendingFlowStore->save([
    'type' => 'qrcode_poll',
    'auth_code' => 'auth-code',
    'expires_at' => time() + 300,
]);
Assert::true($service->hasPendingFlow(), '存在挂起登录流时应检测到 pending flow。');
Assert::same('pending_manual_intervention', $service->state(), '存在挂起登录流时 state 应为 pending_manual_intervention。');
Assert::true($service->shouldBlockBusinessTasks(), '存在挂起登录流时应阻塞业务任务。');

clearLoginState($context, $pendingFlowStore);

function clearLoginState(AppContext $context, LoginPendingFlowStore $pendingFlowStore): void
{
    foreach ($context->authKeys() as $key) {
        $context->setAuth($key, '');
    }

    $pendingFlowStore->clear();
}
