<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

if (!class_exists(Assert::class)) {
    class Assert
    {
        public static function true(bool $condition, string $message = ''): void
        {
            if (!$condition) {
                self::fail($message ?: '期待条件为真。');
            }
        }

        public static function false(bool $condition, string $message = ''): void
        {
            if ($condition) {
                self::fail($message ?: '期待条件为假。');
            }
        }

        public static function same(mixed $expected, mixed $actual, string $message = ''): void
        {
            if ($expected !== $actual) {
                $details = sprintf(' 期望: %s, 实际: %s', var_export($expected, true), var_export($actual, true));
                self::fail(($message ? $message . '。' : '') . '结果不一致。' . $details);
            }
        }

        private static function fail(string $message): void
        {
            throw new RuntimeException($message);
        }
    }
}
