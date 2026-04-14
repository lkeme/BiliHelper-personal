<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2018 ~ 2026
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\WbiSign;

use Bhp\Api\Vip\ApiUser;

class WbiSign
{
    /**
     * @var array{0: string, 1: string}|null
     */
    protected static ?array $cachedWbiKeys = null;
    protected static ?ApiUser $apiUser = null;

    /**
     * @var array|int[]
     */
    protected static array $mixinKeyEncTab = [
        46, 47, 18, 2, 53, 8, 23, 32, 15, 50, 10, 31, 58, 3, 45, 35, 27, 43, 5, 49,
        33, 9, 42, 19, 29, 28, 14, 39, 12, 38, 41, 13, 37, 48, 7, 16, 24, 55, 40,
        61, 26, 17, 0, 1, 60, 51, 30, 4, 22, 25, 54, 21, 56, 59, 6, 63, 57, 62, 11,
        36, 20, 34, 44, 52
    ];

    /**
     * 获取最新的 img_key 和 sub_key
     * @return array
     */
    protected static function getWbiKeys(): array
    {
        $response = self::apiUser()->userNavInfo();
        $keys = self::extractWbiKeys($response);
        if ($keys !== null) {
            self::$cachedWbiKeys = $keys;
            return $keys;
        }

        if (self::$cachedWbiKeys !== null) {
            return self::$cachedWbiKeys;
        }

        $message = (string)($response['message'] ?? 'invalid response');
        $code = (int)($response['code'] ?? -1);
        throw new \RuntimeException("获取 WBI Keys 失败 {$code} -> {$message}");
    }


    /**
     * 对 imgKey 和 subKey 进行字符顺序打乱编码
     * @param string $orig
     * @return string
     */
    protected static function getMixinKey(string $orig): string
    {
        $mixinKey = "";
        foreach (self::$mixinKeyEncTab as $index) {
            $mixinKey .= $orig[$index];
        }
        return substr($mixinKey, 0, 32);
    }

    /**
     * 为请求参数进行 wbi 签名
     * @param array $payload
     * @return array
     */
    public static function encryption(array $payload): array
    {
        // 删除payload中的w_rid和wts
        unset($payload['w_rid']);
        unset($payload['wts']);
        list($img_key, $sub_key) = self::getWbiKeys();
        $mixin_key = self::getMixinKey($img_key . $sub_key);
        $payload['wts'] = time();
        // 按照 key 重排参数
        ksort($payload);
        // 过滤 value 中的 "!'()*" 字符
        array_walk($payload, function (&$value, $key) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
                return;
            }

            if (is_int($value) || is_float($value)) {
                $value = (string)$value;
            }

            if (is_string($value)) {
                $value = str_replace(["!", "'", "(", ")", "*"], "", $value);
            }
        });
        // 序列化参数
        $data = http_build_query($payload);
        // 计算 w_rid
        $payload['w_rid'] = md5($data . $mixin_key);;
        return $payload;
    }

    /**
     * @param array<string, mixed> $response
     * @return array{0: string, 1: string}|null
     */
    protected static function extractWbiKeys(array $response): ?array
    {
        $imgUrl = trim((string)($response['data']['wbi_img']['img_url'] ?? ''));
        $subUrl = trim((string)($response['data']['wbi_img']['sub_url'] ?? ''));
        if ($imgUrl === '' || $subUrl === '') {
            return null;
        }

        $imgKey = self::extractWbiKeyFromUrl($imgUrl);
        $subKey = self::extractWbiKeyFromUrl($subUrl);
        if ($imgKey === '' || $subKey === '') {
            return null;
        }

        return [$imgKey, $subKey];
    }

    /**
     * 处理extractWBI键FromURL
     * @param string $url
     * @return string
     */
    protected static function extractWbiKeyFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }

        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * 初始化strap
     * @param ApiUser $apiUser
     * @return void
     */
    public static function bootstrap(ApiUser $apiUser): void
    {
        self::$apiUser = $apiUser;
    }

    /**
     * 处理API用户
     * @return ApiUser
     */
    private static function apiUser(): ApiUser
    {
        if (self::$apiUser instanceof ApiUser) {
            return self::$apiUser;
        }

        throw new \RuntimeException('WbiSign has not been bootstrapped.');
    }

}
