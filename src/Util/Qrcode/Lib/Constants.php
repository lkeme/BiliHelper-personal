<?php declare(strict_types=1);

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2024 ~ 2025
 *
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \ &   ／l、
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |   （ﾟ､ ｡ ７
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /  　 \、ﾞ ~ヽ   *
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \   　じしf_, )ノ
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 */

namespace Bhp\Util\Qrcode\Lib;

class Constants
{
    const QR_CACHEABLE = false;
    const QR_CACHE_DIR = ''; //dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
    const QR_FIND_BEST_MASK = true;
    const QR_FIND_FROM_RANDOM = false;
    const QR_DEFAULT_MASK = 2;

    // Encoding modes
    const QR_MODE_NUL = -1;
    const QR_MODE_NUM = 0;
    const QR_MODE_AN = 1;
    const QR_MODE_8 = 2;
    const QR_MODE_KANJI = 3;
    const QR_MODE_STRUCTURE = 4;

    // Levels of error correction.
    const QR_ECLEVEL_L = 0;
    const QR_ECLEVEL_M = 1;
    const QR_ECLEVEL_Q = 2;
    const QR_ECLEVEL_H = 3;

    // Supported output formats
    const STRUCTURE_HEADER_BITS = 20;
    const MAX_STRUCTURED_SYMBOLS = 16;

    // Maks
    const N1 = 3;
    const N2 = 3;
    const N3 = 40;
    const N4 = 10;

    const QRSPEC_VERSION_MAX = 40;
    const QRSPEC_WIDTH_MAX = 177;

    const QRCAP_WIDTH = 0;
    const QRCAP_WORDS = 1;
    const QRCAP_REMINDER = 2;
    const QRCAP_EC = 3;
}
