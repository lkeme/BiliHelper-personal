<?php declare(strict_types=1);

namespace Bhp\Util;

use Bhp\Env\Env;
use Bhp\Log\Log;
use JetBrains\PhpStorm\NoReturn;

final class AppTerminator
{
    #[NoReturn]
    public static function fail(mixed $message = 'exit', array $context = [], int $delay = 120): void
    {
        Log::error($message, $context);

        if (Env::isDocker()) {
            sleep($delay);
        } else {
            sleep((int)($delay / 4));
        }

        exit(1);
    }
}
