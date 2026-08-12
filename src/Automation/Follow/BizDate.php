<?php declare(strict_types=1);

namespace Bhp\Automation\Follow;

use DateTimeImmutable;
use RuntimeException;

final class BizDate
{
    /**
     * 标准化Biz日期
     * @param string $bizDate
     * @param string $label
     * @return string
     */
    public static function normalize(string $bizDate, string $label = 'biz_date'): string
    {
        $normalized = trim($bizDate);
        if (!self::isValid($normalized)) {
            throw new RuntimeException(sprintf('%s 格式非法: %s', $label, $normalized));
        }

        return $normalized;
    }

    /**
     * 判断Biz日期是否满足条件
     * @param string $bizDate
     * @return bool
     */
    public static function isValid(string $bizDate): bool
    {
        if ($bizDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bizDate)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $bizDate);
        if (!$date instanceof DateTimeImmutable) {
            return false;
        }

        return $date->format('Y-m-d') === $bizDate;
    }

    /**
     * 处理today
     * @return string
     */
    public static function today(?int $timestamp = null): string
    {
        return date('Y-m-d', $timestamp ?? time());
    }
}
