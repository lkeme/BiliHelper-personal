<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Bhp\Login\LoginManualInterventionPolicy;
use Bhp\Login\LoginPendingFlowStore;
use Bhp\Runtime\AppContext;
use Tests\Support\Assert;

if (!defined('PROFILE_CACHE_PATH')) {
    $cachePath = sys_get_temp_dir() . '/bilihelper-login-manual-cache-' . substr(md5((string)__FILE__), 0, 8);
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0777, true);
    }
    define('PROFILE_CACHE_PATH', $cachePath);
}

$store = new LoginPendingFlowStore();
$store->clear();

$notices = [];
$terminated = [];
$now = time();

$store->save([
    'type' => 'sms_captcha',
    'started_at' => $now - 120,
    'expires_at' => $now + 600,
]);

$policy = new LoginManualInterventionPolicy(
    new AppContext(),
    $store,
    static fn (): int => $now,
    static function (string $type, string $message) use (&$notices): void {
        $notices[] = [$type, $message];
    },
    static function (string $message) use (&$terminated): void {
        $terminated[] = $message;
    },
    'unattended',
    60,
    300,
    900,
);
$policy->enforce();
Assert::same(1, count($notices), '超过通知阈值时应发送人工介入通知。');
Assert::same(0, count($terminated), '未超过超时阈值时不应退出。');

$notices = [];
$terminated = [];
$now = time();
$store->save([
    'type' => 'account_captcha',
    'started_at' => $now - 1200,
    'expires_at' => $now + 600,
]);
$timeoutPolicy = new LoginManualInterventionPolicy(
    new AppContext(),
    $store,
    static fn (): int => $now,
    static function (string $type, string $message) use (&$notices): void {
        $notices[] = [$type, $message];
    },
    static function (string $message) use (&$terminated): void {
        $terminated[] = $message;
    },
    'unattended',
    60,
    300,
    900,
);
$timeoutPolicy->enforce();
Assert::same(1, count($terminated), '无人值守且超过超时阈值时应退出。');
Assert::true(str_contains($terminated[0], '超时退出'), '超时退出消息应包含超时语义。');

$notices = [];
$terminated = [];
$now = time();
$store->save([
    'type' => 'qrcode_poll',
    'started_at' => $now - 1200,
    'expires_at' => $now + 600,
]);
$interactivePolicy = new LoginManualInterventionPolicy(
    new AppContext(),
    $store,
    static fn (): int => $now,
    static function (string $type, string $message) use (&$notices): void {
        $notices[] = [$type, $message];
    },
    static function (string $message) use (&$terminated): void {
        $terminated[] = $message;
    },
    'interactive',
    60,
    300,
    900,
);
$interactivePolicy->enforce();
Assert::same(0, count($terminated), '交互模式下即使超时也不应自动退出。');

$store->clear();
