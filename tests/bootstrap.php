<?php

declare(strict_types=1);

ini_set('display_errors', '1');

require_once __DIR__ . '/Support/Assert.php';

if (!defined('PROFILE_CONFIG_PATH')) {
    $configPath = sys_get_temp_dir() . '/bilihelper-test-config/' ;
    if (!is_dir($configPath)) {
        mkdir($configPath, 0777, true);
    }
    define('PROFILE_CONFIG_PATH', $configPath);
}

if (!defined('PROFILE_LOG_PATH')) {
    $logPath = sys_get_temp_dir() . '/bilihelper-test-log/';
    if (!is_dir($logPath)) {
        mkdir($logPath, 0777, true);
    }
    define('PROFILE_LOG_PATH', $logPath);
}

/**
 * 获取与活动彩池相关的测试夹具路径
 */
if (!function_exists('activityLotteryFixturePath')) {
    function activityLotteryFixturePath(string $name): string
    {
        return __DIR__ . '/Fixtures/activity_lottery/' . $name;
    }
}

$root = dirname(__DIR__);
$vendorAutoload = $root . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    /** @noinspection PhpIncludeInspection */
    require_once $vendorAutoload;
}
if (!defined('ACTIVITY_LOTTERY_INTERNAL_AUTOLOAD')) {
    define('ACTIVITY_LOTTERY_INTERNAL_AUTOLOAD', true);
    spl_autoload_register(function (string $class) use ($root): void {
        $prefix = 'Bhp\\Plugin\\ActivityLottery\\Internal\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $root . '/plugin/ActivityLottery/Internal/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}
