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
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
        $output = new ConsoleOutput();
        static::initQrcodeStyle($output);
        //
        $pxMap[0] = static::isWin() ? '<whitec>mm</whitec>' : '<whitec>  </whitec>';
        $pxMap[1] = '<blackc>  </blackc>';
        //
        $text = QrConsole::text($text);
        $length = strlen($text[0]);
        $output->write("\n");
        //
        foreach ($text as $line) {
            $output->write($pxMap[0]);
            for ($i = 0; $i < $length; $i++) {
                $type = substr($line, $i, 1);
                $output->write($pxMap[$type]);
            }
            $output->writeln($pxMap[0]);
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

    /**
     * init qrCode style.
     * @param OutputInterface $output
     */
    protected static function initQrcodeStyle(OutputInterface $output): void
    {
        $style = new OutputFormatterStyle('black', 'black', ['bold']);
        $output->getFormatter()->setStyle('blackc', $style);
        $style = new OutputFormatterStyle('white', 'white', ['bold']);
        $output->getFormatter()->setStyle('whitec', $style);
    }
}
