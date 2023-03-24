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

namespace Bhp\Util\Qrcode\Lib;

use Exception;

class QrEncode
{
    public bool $case_sensitive = true;
    public bool $eight_bit = false;

    public int $version = 0;
    public int $size = 3;
    public int $margin = 4;

    public int $structured = 0; // not supported yet

    public int $level = Constants::QR_ECLEVEL_L;
    public int $hint = Constants::QR_MODE_8;

    /**
     * @param int $level
     * @param int $size
     * @param int $margin
     * @return QrEncode
     */
    public static function factory(int $level = Constants::QR_ECLEVEL_L, int $size = 3, int $margin = 4): QrEncode
    {
        $enc = new QrEncode();
        $enc->size = $size;
        $enc->margin = $margin;

        $enc->level = match ($level . '') {
            '0', '1', '2', '3' => $level,
            'l', 'L' => Constants::QR_ECLEVEL_L,
            'm', 'M' => Constants::QR_ECLEVEL_M,
            'q', 'Q' => Constants::QR_ECLEVEL_Q,
            'h', 'H' => Constants::QR_ECLEVEL_H,
        };
        return $enc;
    }

    /**
     * @param string $text
     * @return array
     * @throws Exception
     */
    public function encode(string $text): array
    {
        $code = new QrCode();

        if ($this->eight_bit) {
            $code->encodeString8bit($text, $this->version, $this->level);
        } else {
            $code->encodeString($text, $this->version, $this->level, $this->hint, $this->case_sensitive);
        }

        return QrTools::binarize($code->data);
    }

}
