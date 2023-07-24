<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2023 ~ 2024
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\LiveSign;

use Bhp\Util\DesignPattern\SingleTon;

class LiveSign extends SingleTon
{

    // ["sha512", "sha3_512", "sha384", "sha3_384", "blake2b"]:
    protected static array $algorithm = [
        0 => 'HMAC-MD5',
        1 => 'HMAC-SHA1',
        2 => 'HMAC-SHA256',
        3 => 'HMAC-SHA224',
        4 => 'HMAC-SHA512',
        5 => 'HMAC-SHA384',
    ];

    /**
     * @var array|int[]
     */
    protected array $default_app_r = [2, 5, 1, 4];

    /**
     * @var string
     */
    protected string $default_app_benchmark = 'seacasdgyijfhofiuxoannn';

    /**
     * @var array|int[]
     */
    protected array $default_pc_r = [2, 5, 1, 4];

    /**
     * @var string
     */
    protected string $default_pc_benchmark = '';

    /**
     * @return void
     */
    public function init(): void
    {

    }

    /**
     * APP
     * @param string $benchmark
     * @param array $r
     * @param array $data
     * @return string
     */
    public static function app(string $benchmark, array $r, array $data): string
    {
        $_data = json_encode($data);
        foreach ($r as $key) {
            $_data = hash_hmac(static::$algorithm[$key], $_data, $benchmark);
        }
        return $_data;
    }


    /**
     * PC
     * @param string $benchmark
     * @param array $r
     * @param array $data
     * @return string
     */
    public static function pc(string $benchmark, array $r, array $data): string
    {

    }


}
