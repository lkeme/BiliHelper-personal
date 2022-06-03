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

class FrameFiller
{
    public int $width;
    public array $frame;
    public int $x;
    public int $y;
    public int $dir;
    public int $bit;

    /**
     * @param $width
     * @param $frame
     */
    public function __construct($width, &$frame)
    {
        $this->width = $width;
        $this->frame = $frame;
        $this->x = $width - 1;
        $this->y = $width - 1;
        $this->dir = -1;
        $this->bit = -1;
    }

    /**
     * @param array $at
     * @param int $val
     * @return void
     */
    public function setFrameAt(array $at, int $val): void
    {
        $this->frame[$at['y']][$at['x']] = chr($val);
    }

    /**
     * @param array $at
     * @return int
     */
    public function getFrameAt(array $at): int
    {
        return ord($this->frame[$at['y']][$at['x']]);
    }

    /**
     * @return array|null
     */
    public function next(): ?array
    {
        do {
            if ($this->bit == -1) {
                $this->bit = 0;
                return array('x' => $this->x, 'y' => $this->y);
            }

            $x = $this->x;
            $y = $this->y;
            $w = $this->width;

            if ($this->bit == 0) {
                $x--;
                $this->bit++;
            } else {
                $x++;
                $y += $this->dir;
                $this->bit--;
            }

            if ($this->dir < 0) {
                if ($y < 0) {
                    $y = 0;
                    $x -= 2;
                    $this->dir = 1;
                    if ($x == 6) {
                        $x--;
                        $y = 9;
                    }
                }
            } else {
                if ($y == $w) {
                    $y = $w - 1;
                    $x -= 2;
                    $this->dir = -1;
                    if ($x == 6) {
                        $x--;
                        $y -= 8;
                    }
                }
            }
            if ($x < 0 || $y < 0) return null;

            $this->x = $x;
            $this->y = $y;

        } while (ord($this->frame[$y][$x]) & 0x80);

        return array('x' => $x, 'y' => $y);
    }

}
