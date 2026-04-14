<?php declare(strict_types=1);

namespace Bhp\Util;

use Bhp\Env\Env;
use JetBrains\PhpStorm\NoReturn;

final class AppTerminator
{
    #[NoReturn]
    /**
     * 处理fail
     * @param mixed $message
     * @param array $context
     * @param int $delay
     * @return void
     */
    public static function fail(mixed $message = 'exit', array $context = [], int $delay = 120): void
    {
        self::writeError($message, $context);

        if (Env::isDocker()) {
            sleep($delay);
        } else {
            sleep((int)($delay / 4));
        }

        exit(1);
    }

    /**
     * 处理writeError
     * @param mixed $message
     * @param array $context
     * @return void
     */
    private static function writeError(mixed $message, array $context): void
    {
        $normalizedMessage = trim((string)$message);
        $parts = [];
        if ($normalizedMessage !== '' && $normalizedMessage !== 'exit') {
            $parts[] = $normalizedMessage;
        }
        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encoded) && $encoded !== '') {
                $parts[] = $encoded;
            }
        }
        if ($parts === []) {
            return;
        }

        fwrite(STDERR, implode(' ', $parts) . PHP_EOL);
    }
}
