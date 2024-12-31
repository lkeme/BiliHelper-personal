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
use Bhp\Util\DesignPattern\SingleTon;

class WbiSign extends SingleTon
{
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
     * @return void
     */
    public function init(): void
    {

    }

    /**
     * 获取最新的 img_key 和 sub_key
     * @return array
     */
    protected static function getWbiKeys(): array
    {
        $response = ApiUser::userNavInfo();
        //
        $img_url = $response['data']['wbi_img']['img_url'];
        $sub_url = $response['data']['wbi_img']['sub_url'];
        $img_key = explode('.', array_slice(explode('/', $img_url), -1)[0])[0];
        $sub_key = explode('.', array_slice(explode('/', $sub_url), -1)[0])[0];

        return [$img_key, $sub_key];
    }


    /**
     * 对 imgKey 和 subKey 进行字符顺序打乱编码
     * @param string $orig
     * @return string
     */
    protected static function getMixinKey(string $orig): string
    {
        $mixinKey = "";
        //
        foreach (self::$mixinKeyEncTab as $index) {
            $mixinKey .= $orig[$index];
        }
        //
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
        //
        list($img_key, $sub_key) = self::getWbiKeys();
        //
        $mixin_key = self::getMixinKey($img_key . $sub_key);
        $payload['wts'] = round(time());
        // 按照 key 重排参数
        ksort($payload);
        // 过滤 value 中的 "!'()*" 字符
        array_walk($payload, function (&$value, $key) {
            $value = str_replace(["!", "'", "(", ")", "*"], "", $value);
        });
        // 序列化参数
        $data = http_build_query($payload);
        // 计算 w_rid
        $payload['w_rid'] = md5($data . $mixin_key);;
        //
        return $payload;
    }

}
