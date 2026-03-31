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

namespace Bhp\Util\Qrcode;

use Bhp\Util\Qrcode\Lib\QrCode as QrConsole;

define('QR_CACHEABLE', true);                                                               // use cache - more disk reads but less CPU power, masks and format templates are stored there
define('QR_CACHE_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR);  // used when QR_CACHEABLE === true
define('QR_LOG_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);                                // default error logs dir
define('QR_FIND_BEST_MASK', true);                                                          // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
define('QR_FIND_FROM_RANDOM', false);                                                       // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
define('QR_DEFAULT_MASK', 2);                                                               // when QR_FIND_BEST_MASK === false
define('QR_PNG_MAXIMUM_SIZE', 1024);
// Supported output formats
define('QR_FORMAT_TEXT', 0);
define('QR_FORMAT_PNG', 1);

class Qrcode
{

    /**
     * show qrCode on console.
     *
     * @param string $text
     *
     * @return void
     */
    public static function show(string $text): void
    {
        static::enableVt100IfPossible();

        $pxMap[0] = static::whiteBlock();
        $pxMap[1] = static::blackBlock();
        //
        $text = QrConsole::text($text);
        $length = strlen($text[0]);
        fwrite(STDOUT, PHP_EOL);
        //
        foreach ($text as $line) {
            fwrite(STDOUT, $pxMap[0]);
            for ($i = 0; $i < $length; $i++) {
                $type = substr($line, $i, 1);
                fwrite(STDOUT, $pxMap[$type]);
            }
            fwrite(STDOUT, $pxMap[0] . PHP_EOL);
        }
    }

    /**
     * determine the console is windows or linux.
     * @return bool
     */
    protected static function isWin(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    protected static function enableVt100IfPossible(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' && function_exists('sapi_windows_vt100_support')) {
            @sapi_windows_vt100_support(STDOUT, true);
        }
    }

    protected static function blackBlock(): string
    {
        return "\033[40m  \033[0m";
    }

    protected static function whiteBlock(): string
    {
        return static::isWin() ? "\033[47mmm\033[0m" : "\033[47m  \033[0m";
    }
}
