<?php

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


namespace Tests;

use Bhp\Util\AsciiTable\AsciiTable;
use PHPUnit\Framework\TestCase;

class AsciiTableTest extends TestCase
{

    /**
     * @doesNotPerformAssertions
     */
    public function testGetTable()
    {
        $data = [
            [
                'firstname' => 'Mo 啊大苏打allie',
                'surname' => 'Alv萨达速度asarez',
                'email' => 'molliealvarez@example.com',
            ],
            [
                'firstname' => 'Dianna',
                'surname' => 'Mcbride',
                'age' => 1111,
                'email' => 'diannamcbride@example.com',
            ],
            [
                'firstname' => '撒旦撒旦asra',
                'surname' => 'Muel大大是打算的ler',
                'age' => 50,
                'email' => 'elviramueller@example.com',
            ],
            [
                'firstname' => 'Corine',
                'surname' => 'Morton',
                'age' => 0,
            ],
            [
                'firstname' => 'James',
                'surname' => 'Allison',
            ],
            [
                'firstname' => 'Bowen这是哥',
                'surname' => 'Kelley',
                'age' => 50,
                'email' => 'bowenkelley@example.com',
            ],
        ];
        $renderer = new AsciiTable($data);
        echo $renderer->getTable();
    }
}
