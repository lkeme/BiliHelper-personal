<?php declare(strict_types=1);

namespace Bhp\Api\Support;

use Throwable;

final class ApiJson
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(string $raw, string $label): array
    {
        if ($raw === '') {
            return [
                'code' => -500,
                'message' => "{$label} 响应为空",
                'data' => [],
            ];
        }

        $raw = self::normalizeRawJsonResponse($raw);
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 响应解析失败",
                'data' => [],
            ];
        }

        if (!is_array($decoded)) {
            return [
                'code' => -500,
                'message' => "{$label} 响应格式异常",
                'data' => [],
            ];
        }

        return $decoded;
    }

    public static function normalizeRawJsonResponse(string $raw): string
    {
        $raw = self::decodeCompressedResponse($raw);
        $raw = self::normalizeUtf8($raw);
        $raw = preg_replace('/^\xEF\xBB\xBF/u', '', $raw) ?? $raw;
        $raw = trim($raw);

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw) ?? $raw;

        return trim($raw);
    }

    protected static function decodeCompressedResponse(string $raw): string
    {
        if ($raw === '') {
            return $raw;
        }

        if (self::looksLikePlainJson($raw)) {
            return $raw;
        }

        if (str_starts_with($raw, "\x1f\x8b")) {
            $decoded = @gzdecode($raw);
            if (is_string($decoded) && $decoded !== '' && self::looksLikeDecodedText($decoded)) {
                return $decoded;
            }
        }

        if (self::looksLikeZlibStream($raw)) {
            $decoded = @zlib_decode($raw);
            if (is_string($decoded) && $decoded !== '' && self::looksLikeDecodedText($decoded)) {
                return $decoded;
            }
        }

        return $raw;
    }

    private static function looksLikePlainJson(string $raw): bool
    {
        $trimmed = ltrim($raw);
        if ($trimmed === '') {
            return false;
        }

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    private static function looksLikeDecodedText(string $raw): bool
    {
        if ($raw === '') {
            return false;
        }

        if (self::looksLikePlainJson($raw)) {
            return true;
        }

        return preg_match('//u', $raw) === 1;
    }

    private static function looksLikeZlibStream(string $raw): bool
    {
        if (strlen($raw) < 2) {
            return false;
        }

        $cmf = ord($raw[0]);
        $flg = ord($raw[1]);

        if (($cmf & 0x0F) !== 0x08) {
            return false;
        }

        if ((($cmf << 8) + $flg) % 31 !== 0) {
            return false;
        }

        return true;
    }

    private static function normalizeUtf8(string $raw): string
    {
        if ($raw === '') {
            return $raw;
        }

        if (preg_match('//u', $raw) === 1) {
            return $raw;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5,ISO-8859-1');
            if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $raw);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        return $raw;
    }

}
