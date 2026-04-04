<?php declare(strict_types=1);

namespace Bhp\Api\Support;

use Bhp\Log\Log;
use Bhp\Request\Request;
use Throwable;

final class ApiJson
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public static function get(
        string $os,
        string $url,
        array $payload = [],
        array $headers = [],
        string $label = '',
    ): array
    {
        $label = $label !== '' ? $label : self::buildLabel('get', $url);
        try {
            $raw = Request::get($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return self::decode($raw, $label);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public static function post(
        string $os,
        string $url,
        array $payload = [],
        array $headers = [],
        string $label = '',
    ): array
    {
        $label = $label !== '' ? $label : self::buildLabel('post', $url);
        try {
            $raw = Request::post($os, $url, $payload, $headers);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => "{$label} 请求失败: {$throwable->getMessage()}",
                'data' => [],
            ];
        }

        return self::decode($raw, $label);
    }

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
            Log::warning("{$label} 响应解析失败: {$throwable->getMessage()}");
            Log::debug("{$label} raw: {$raw}");

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

        if (str_starts_with($raw, "\x1f\x8b")) {
            $decoded = @gzdecode($raw);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        $decoded = @zlib_decode($raw);
        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }

        return $raw;
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

    private static function buildLabel(string $method, string $url): string
    {
        $parts = parse_url($url);
        $host = trim((string)($parts['host'] ?? ''));
        $path = trim((string)($parts['path'] ?? ''));
        $path = trim(str_replace('/', '.', $path), '.');

        $segments = array_values(array_filter([
            'api_json',
            strtolower(trim($method)),
            $host !== '' ? str_replace('-', '_', $host) : '',
            $path !== '' ? str_replace('-', '_', $path) : '',
        ]));

        return implode('.', $segments);
    }
}
