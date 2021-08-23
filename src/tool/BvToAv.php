<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2021 ~ 2022
 *  Source: https://github.com/anhao/bv2av/
 */

namespace BiliHelper\Tool;

class BvToAv
{
    protected string $tr = "fZodR9XQDSUm21yCkr6zBqiveYah8bt4xsWpHnJE7jL5VG3guMTKNPAwcF";
    protected int $xor = 177451812;
    protected int $add = 8728348608;
    protected array $s = [11, 10, 3, 8, 4, 6];


    /**
     * @use BV 转 AV
     * @param $bv
     * @return int|string
     */
    public function dec($bv): int|string
    {
        $r = 0;
        $tr = array_flip(str_split($this->tr));
        for ($i = 0; $i < 6; $i++) {
            $r += $tr[$bv[$this->s[$i]]] * (pow(58, $i));
        }
        return ($r - $this->add) ^ $this->xor;
    }


    /**
     * @use AV 转 BV
     * @param $av
     * @return string
     */
    public function enc($av): string
    {
        $tr = str_split($this->tr);
        $bv = 'BV1  4 1 7  ';
        $av = ($av ^ $this->xor) + $this->add;
        for ($i = 0; $i < 6; $i++) {
            $bv[$this->s[$i]] = $tr[floor($av / pow(58, $i) % 58)];
        }
        return $bv;
    }
}