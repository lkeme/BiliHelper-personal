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

class QrSplit
{

    public string $data_str = '';
    public QrInput $input;
    public int $mode_hint;

    /**
     * @param string $data_str
     * @param QrInput $input
     * @param int $mode_hint
     */
    public function __construct(string $data_str, QrInput $input, int $mode_hint)
    {
        $this->data_str = $data_str;
        $this->input = $input;
        $this->mode_hint = $mode_hint;
    }

    /**
     * @param string $str
     * @param int $pos
     * @return bool
     */
    public static function isDigit(string $str, int $pos): bool
    {
        if ($pos >= strlen($str))
            return false;

        return ((ord($str[$pos]) >= ord('0')) && (ord($str[$pos]) <= ord('9')));
    }

    /**
     * @param string $str
     * @param int $pos
     * @return bool
     */
    public static function isalnumat(string $str, int $pos): bool
    {
        if ($pos >= strlen($str))
            return false;

        return (QrInput::lookAnTable(ord($str[$pos])) >= 0);
    }

    /**
     * @param int $pos
     * @return int
     */
    public function identifyMode(int $pos): int
    {
        if ($pos >= strlen($this->data_str))
            return Constants::QR_MODE_NUL;

        $c = $this->data_str[$pos];

        if (self::isDigit($this->data_str, $pos)) {
            return Constants::QR_MODE_NUM;
        } else if (self::isalnumat($this->data_str, $pos)) {
            return Constants::QR_MODE_AN;
        } else if ($this->mode_hint == Constants::QR_MODE_KANJI) {

            if ($pos + 1 < strlen($this->data_str)) {
                $d = $this->data_str[$pos + 1];
                $word = (ord($c) << 8) | ord($d);
                if (($word >= 0x8140 && $word <= 0x9ffc) || ($word >= 0xe040 && $word <= 0xebbf)) {
                    return Constants::QR_MODE_KANJI;
                }
            }
        }

        return Constants::QR_MODE_8;
    }

    /**
     * @return int|mixed
     */
    public function eatNum(): mixed
    {
        $ln = QrSpec::lengthIndicator(Constants::QR_MODE_NUM, $this->input->getVersion());

        $p = 0;
        while (self::isDigit($this->data_str, $p)) {
            $p++;
        }

        $run = $p;
        $mode = $this->identifyMode($p);

        if ($mode == Constants::QR_MODE_8) {
            $dif = QrInput::estimateBitsModeNum($run) + 4 + $ln
                + QrInput::estimateBitsMode8(1)         // + 4 + l8
                - QrInput::estimateBitsMode8($run + 1); // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }
        if ($mode == Constants::QR_MODE_AN) {
            $dif = QrInput::estimateBitsModeNum($run) + 4 + $ln
                + QrInput::estimateBitsModeAn(1)        // + 4 + la
                - QrInput::estimateBitsModeAn($run + 1);// - 4 - la
            if ($dif > 0) {
                return $this->eatAn();
            }
        }

        $ret = $this->input->append(Constants::QR_MODE_NUM, $run, str_split($this->data_str));
        if ($ret < 0)
            return -1;

        return $run;
    }

    /**
     * @return int|mixed
     */
    public function eatAn(): mixed
    {
        $la = QrSpec::lengthIndicator(Constants::QR_MODE_AN, $this->input->getVersion());
        $ln = QrSpec::lengthIndicator(Constants::QR_MODE_NUM, $this->input->getVersion());

        $p = 0;

        while (self::isalnumat($this->data_str, $p)) {
            if (self::isDigit($this->data_str, $p)) {
                $q = $p;
                while (self::isDigit($this->data_str, $q)) {
                    $q++;
                }

                $dif = QrInput::estimateBitsModeAn($p) // + 4 + la
                    + QrInput::estimateBitsModeNum($q - $p) + 4 + $ln
                    - QrInput::estimateBitsModeAn($q); // - 4 - la

                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                $p++;
            }
        }

        $run = $p;

        if (!self::isalnumat($this->data_str, $p)) {
            $dif = QrInput::estimateBitsModeAn($run) + 4 + $la
                + QrInput::estimateBitsMode8(1) // + 4 + l8
                - QrInput::estimateBitsMode8($run + 1); // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }

        $ret = $this->input->append(Constants::QR_MODE_AN, $run, str_split($this->data_str));
        if ($ret < 0)
            return -1;

        return $run;
    }

    /**
     * @return int
     */
    public function eatKanji(): int
    {
        $p = 0;

        while ($this->identifyMode($p) == Constants::QR_MODE_KANJI) {
            $p += 2;
        }

        $ret = $this->input->append(Constants::QR_MODE_KANJI, $p, str_split($this->data_str));
        if ($ret < 0)
            return -1;

        return $ret;
    }

    /**
     * @return int|mixed
     */
    public function eat8(): mixed
    {
        $la = QrSpec::lengthIndicator(Constants::QR_MODE_AN, $this->input->getVersion());
        $ln = QrSpec::lengthIndicator(Constants::QR_MODE_NUM, $this->input->getVersion());

        $p = 1;
        $data_strLen = strlen($this->data_str);

        while ($p < $data_strLen) {

            $mode = $this->identifyMode($p);
            if ($mode == Constants::QR_MODE_KANJI) {
                break;
            }
            if ($mode == Constants::QR_MODE_NUM) {
                $q = $p;
                while (self::isDigit($this->data_str, $q)) {
                    $q++;
                }
                $dif = QrInput::estimateBitsMode8($p) // + 4 + l8
                    + QrInput::estimateBitsModeNum($q - $p) + 4 + $ln
                    - QrInput::estimateBitsMode8($q); // - 4 - l8
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else if ($mode == Constants::QR_MODE_AN) {
                $q = $p;
                while (self::isalnumat($this->data_str, $q)) {
                    $q++;
                }
                $dif = QrInput::estimateBitsMode8($p)  // + 4 + l8
                    + QrInput::estimateBitsModeAn($q - $p) + 4 + $la
                    - QrInput::estimateBitsMode8($q); // - 4 - l8
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                $p++;
            }
        }

        $run = $p;
        $ret = $this->input->append(Constants::QR_MODE_8, $run, str_split($this->data_str));

        if ($ret < 0)
            return -1;

        return $run;
    }


    /**
     * @return int|void
     */
    public function splitString()
    {
        while (strlen($this->data_str) > 0) {
            if ($this->data_str == '')
                return 0;

            $mode = $this->identifyMode(0);

            switch ($mode) {
                case Constants::QR_MODE_NUM:
                    $length = $this->eatNum();
                    break;
                case Constants::QR_MODE_AN:
                    $length = $this->eatAn();
                    break;
                case Constants::QR_MODE_KANJI:
                    if ($hint == Constants::QR_MODE_KANJI)
                        $length = $this->eatKanji();
                    else    $length = $this->eat8();
                    break;
                default:
                    $length = $this->eat8();
                    break;

            }

            if ($length == 0) return 0;
            if ($length < 0) return -1;

            $this->data_str = substr($this->data_str, $length);
        }
    }

    /**
     * @return string
     */
    public function toUpper(): string
    {
        $stringLen = strlen($this->data_str);
        $p = 0;

        while ($p < $stringLen) {
            $mode = self::identifyMode((int)substr($this->data_str, $p), $this->mode_hint);
            if ($mode == Constants::QR_MODE_KANJI) {
                $p += 2;
            } else {
                if (ord($this->data_str[$p]) >= ord('a') && ord($this->data_str[$p]) <= ord('z')) {
                    $this->data_str[$p] = chr(ord($this->data_str[$p]) - 32);
                }
                $p++;
            }
        }

        return $this->data_str;
    }

    /**
     * @param string|null $string $string
     * @param QrInput $input
     * @param $mode_hint
     * @param bool $case_sensitive
     * @return int|void
     * @throws Exception
     */
    public static function splitStringToQrInput(?string $string, QrInput $input, $mode_hint, bool $case_sensitive = true)
    {
        if ($string == '\0' || $string == '') {
            throw new Exception('empty string!!!');
        }

        $split = new QrSplit($string, $input, $mode_hint);

        if (!$case_sensitive)
            $split->toUpper();

        return $split->splitString();
    }
}
