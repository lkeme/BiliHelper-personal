<?php declare(strict_types=1);

namespace Bhp\Env;

final class PhpWarningFilter
{
    private static bool $installed = false;

    private static mixed $previousHandler = null;

    public static function install(): void
    {
        if (self::$installed) {
            return;
        }

        self::$previousHandler = set_error_handler(self::handle(...));
        self::$installed = true;
    }

    public static function shouldSuppress(int $severity, string $message): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        if (!in_array($severity, [E_WARNING, E_USER_WARNING], true)) {
            return false;
        }

        return str_contains($message, "Could not load the system's DNS configuration; falling back to synchronous, blocking resolver;")
            && str_contains($message, 'Could not fetch DNS servers from WMI');
    }

    private static function handle(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (self::shouldSuppress($severity, $message)) {
            return true;
        }

        if (is_callable(self::$previousHandler)) {
            return (bool) call_user_func(self::$previousHandler, $severity, $message, $file, $line);
        }

        return false;
    }
}
