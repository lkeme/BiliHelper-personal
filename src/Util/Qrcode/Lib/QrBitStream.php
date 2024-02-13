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

class QrBitStream
{

    public array $data = [];


    /**
     * @return int
     */
    public function size(): int
    {
        return count($this->data);
    }

    /**
     * @param int $setLength
     * @return int
     */
    public function allocate(int $setLength): int
    {
        $this->data = array_fill(0, $setLength, 0);
        return 0;
    }

    /**
     * @param int $bits
     * @param int $num
     * @return QrBitStream
     */
    public static function newFromNum(int $bits, int $num): QrBitStream
    {
        $bstream = new QrBitStream();
        $bstream->allocate($bits);

        $mask = 1 << ($bits - 1);
        for ($i = 0; $i < $bits; $i++) {
            if ($num & $mask) {
                $bstream->data[$i] = 1;
            } else {
                $bstream->data[$i] = 0;
            }
            $mask = $mask >> 1;
        }

        return $bstream;
    }

    /**
     * @param int $size
     * @param array $data
     * @return QrBitStream
     */
    public static function newFromBytes(int $size, array $data): QrBitStream
    {
        $bstream = new QrBitStream();
        $bstream->allocate($size * 8);
        $p = 0;

        for ($i = 0; $i < $size; $i++) {
            $mask = 0x80;
            for ($j = 0; $j < 8; $j++) {
                if ($data[$i] & $mask) {
                    $bstream->data[$p] = 1;
                } else {
                    $bstream->data[$p] = 0;
                }
                $p++;
                $mask = $mask >> 1;
            }
        }

        return $bstream;
    }

    /**
     * @param QrBitStream $arg
     * @return int
     */
    public function append(QrBitStream $arg): int
    {
        if (is_null($arg)) {
            return -1;
        }

        if ($arg->size() == 0) {
            return 0;
        }

        if ($this->size() == 0) {
            $this->data = $arg->data;
            return 0;
        }

        $this->data = array_values(array_merge($this->data, $arg->data));

        return 0;
    }

    /**
     * @param int $bits
     * @param int $num
     * @return int
     */
    public function appendNum(int $bits, int $num): int
    {
        if ($bits == 0)
            return 0;

        $b = QrBitStream::newFromNum($bits, $num);

        if (is_null($b))
            return -1;

        $ret = $this->append($b);
        unset($b);

        return $ret;
    }

    /**
     * @param int $size
     * @param array $data
     * @return int
     */
    public function appendBytes(int $size, array $data): int
    {
        if ($size == 0)
            return 0;

        $b = QrBitStream::newFromBytes($size, $data);

        if (is_null($b))
            return -1;

        $ret = $this->append($b);
        unset($b);

        return $ret;
    }

    /**
     * @return array
     */
    public function toByte(): array
    {

        $size = $this->size();

        if ($size == 0) {
            return [];
        }

        $data = array_fill(0, (int)(($size + 7) / 8), 0);
        $bytes = (int)($size / 8);

        $p = 0;

        for ($i = 0; $i < $bytes; $i++) {
            $v = 0;
            for ($j = 0; $j < 8; $j++) {
                $v = $v << 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$i] = $v;
        }

        if ($size & 7) {
            $v = 0;
            for ($j = 0; $j < ($size & 7); $j++) {
                $v = $v << 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$bytes] = $v;
        }

        return $data;
    }

}
