<?php declare(strict_types=1);

if (!defined('ACTIVITY_LOTTERY_INTERNAL_AUTOLOAD')) {
    define('ACTIVITY_LOTTERY_INTERNAL_AUTOLOAD', true);
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Bhp\\Plugin\\ActivityLottery\\Internal\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}
