<?php

namespace BiliHelper\Tool;

use PHPUnit\Framework\TestCase;

class ArrayToTextTableTest extends TestCase
{
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
        $renderer = new ArrayToTextTable($data);
        echo $renderer->getTable();
    }
}
