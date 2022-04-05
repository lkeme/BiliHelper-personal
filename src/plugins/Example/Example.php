<?php

/**
 *   _____   _   _       _   _   _   _____   _       _____   _____   _____
 *  |  _  \ | | | |     | | | | | | | ____| | |     |  _  \ | ____| |  _  \
 *  | |_| | | | | |     | | | |_| | | |__   | |     | |_| | | |__   | |_| |
 *  |  _  { | | | |     | | |  _  | |  __|  | |     |  ___/ |  __|  |  _  /
 *  | |_| | | | | |___  | | | | | | | |___  | |___  | |     | |___  | | \ \
 *  |_____/ |_| |_____| |_| |_| |_| |_____| |_____| |_|     |_____| |_|  \_\
 *
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2022 ~ 2023
 *
 *  &   ／l、
 *    （ﾟ､ ｡ ７
 *   　\、ﾞ ~ヽ   *
 *   　じしf_, )ノ
 *
 */

namespace BiliHelper\Plugins\Example;

use BiliHelper\Plugins\Plugins;

class Example
{

    public function __construct()
    {
        $Manager = Plugins::getInstance();
        $Manager->register('Example', $this, 'example');
    }


    public function example($example): string
    {
        return "插件" . $example;
    }

}