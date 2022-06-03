<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Util\Qrcode\Lib;

class QrStr
{
    /**
     * @param array $src_tab
     * @param int $x
     * @param int $y
     * @param string $repl
     * @param int|bool $replLen
     * @return void
     */
    public static function set(array &$src_tab, int $x, int $y, string $repl, int|bool $replLen = false): void
    {
        $src_tab[$y] = substr_replace($src_tab[$y], ($replLen !== false) ? substr($repl, 0, $replLen) : $repl, $x, ($replLen !== false) ? $replLen : strlen($repl));
    }
}