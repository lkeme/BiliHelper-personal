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

use Exception;

class QrCode
{

    public int $version;
    public int $width;
    public array $data;

    //----------------------------------------------------------------------
    public function encodeMask(QrInput $input, $mask)
    {
        if ($input->getVersion() < 0 || $input->getVersion() > Constants::QRSPEC_VERSION_MAX) {
            throw new Exception('wrong version');
        }
        if ($input->getErrorCorrectionLevel() > Constants::QR_ECLEVEL_H) {
            throw new Exception('wrong level');
        }

        $raw = new QrRawCode($input);

        $version = $raw->version;
        $width = QrSpec::getWidth($version);
        $frame = QrSpec::newFrame($version);

        $filler = new FrameFiller($width, $frame);
        if (is_null($filler)) {
            return NULL;
        }

        // inteleaved data and ecc codes
        for ($i = 0; $i < $raw->dataLength + $raw->eccLength; $i++) {
            $code = $raw->getCode();
            $bit = 0x80;
            for ($j = 0; $j < 8; $j++) {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }

        unset($raw);

        // remainder bits
        $j = QrSpec::getRemainder($version);
        for ($i = 0; $i < $j; $i++) {
            $addr = $filler->next();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);


        // masking
        $maskObj = new QrMask();
        if ($mask < 0) {

            if (Constants::QR_FIND_BEST_MASK) {
                $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
            } else {
                $masked = $maskObj->makeMask($width, $frame, (intval(Constants::QR_DEFAULT_MASK) % 8), $input->getErrorCorrectionLevel());
            }
        } else {
            $masked = $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
        }

        if ($masked == NULL) {
            return NULL;
        }


        $this->version = $version;
        $this->width = $width;
        $this->data = $masked;

        return $this;
    }

    //----------------------------------------------------------------------
    public function encodeInput(QrInput $input)
    {
        return $this->encodeMask($input, -1);
    }

    //----------------------------------------------------------------------
    public function encodeString8bit($string, $version, $level)
    {
        if ($string == NULL) {
            throw new Exception('empty string!');
            return NULL;
        }

        $input = new QrInput($version, $level);
        if ($input == NULL) return NULL;

        $ret = $input->append($input, Constants::QR_MODE_8, strlen($string), str_split($string));
        if ($ret < 0) {
            unset($input);
            return NULL;
        }
        return $this->encodeInput($input);
    }

    //----------------------------------------------------------------------
    public function encodeString($string, $version, $level, $hint, $case_sensitive)
    {

        if ($hint != Constants::QR_MODE_8 && $hint != Constants::QR_MODE_KANJI) {
            throw new Exception('bad hint');
            return NULL;
        }

        $input = new QrInput($version, $level);
        if ($input == NULL) return NULL;

        $ret = QrSplit::splitStringToQrInput($string, $input, $hint, $case_sensitive);
        if ($ret < 0) {
            return NULL;
        }

        return $this->encodeInput($input);
    }

    //----------------------------------------------------------------------
    public static function png($text, $outfile = false, $level = Constants::QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint = false)
    {
        $enc = QrEncode::factory($level, $size, $margin);
        return $enc->encodePNG($text, $outfile, $saveandprint);
    }

    //----------------------------------------------------------------------
    public static function text($text, $outfile = false, $level = Constants::QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = QrEncode::factory($level, $size, $margin);
        return $enc->encode($text, $outfile);
    }

    //----------------------------------------------------------------------
    public static function raw($text, $outfile = false, $level = Constants::QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = QrEncode::factory($level, $size, $margin);
        return $enc->encodeRAW($text, $outfile);
    }
}
